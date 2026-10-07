<?php
/** Front-end playlist and gallery rendering. */
defined( 'ABSPATH' ) || exit;

final class WP_YouTube {
	const CACHE_TTL = 21600;
	const MAX_ITEMS = 50;
	private static $priority_used = false;

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'template_redirect', array( __CLASS__, 'thumbnail_request' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'settings_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_filter( 'render_block_core/embed', array( __CLASS__, 'render_embed' ), 20, 2 );
		add_shortcode( 'wp_youtube', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'embedyt', array( __CLASS__, 'legacy_shortcode' ) );
		// After do_blocks (9), before do_shortcode (11).
		add_filter( 'the_content', array( __CLASS__, 'normalize_shortcodes' ), 10 );
	}

	/**
	 * A stray or missing closing tag made WordPress treat everything up to the
	 * next [/embedyt] or [/wp_youtube] as the shortcode's content and replace
	 * it, cutting blocks (and their styles and closing tags) out of the page.
	 * Neither shortcode needs enclosed content: [embedyt]URL[/embedyt] and an
	 * unclosed [embedyt]URL become [embedyt url="URL"], and the remaining
	 * closing tags are removed, so no shortcode can swallow the page.
	 */
	public static function normalize_shortcodes( $content ) {
		if ( ! is_string( $content ) || ( false === stripos( $content, '[embedyt' ) && false === stripos( $content, '[/wp_youtube' ) ) ) {
			return $content;
		}
		$to_url = static function ( $matches ) {
			$atts = isset( $matches[1] ) ? $matches[1] : '';
			return '[embedyt' . $atts . ' url="' . esc_attr( html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) . '"]';
		};
		$content = preg_replace_callback( '~\[embedyt(\s[^\]]*)?\]\s*([^\[\]<>\s]+)\s*\[/embedyt\]~i', $to_url, $content );
		$content = preg_replace_callback( '~\[embedyt(\s[^\]]*)?\]\s*(https?://[^\s\[\]<>"\']+)~i', $to_url, (string) $content );
		return (string) preg_replace( '~\[/(?:embedyt|wp_youtube)\]~i', '', (string) $content );
	}

	/** A message for people who can edit the page; visitors get nothing. */
	private static function notice( $message ) {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="wpy-notice" role="note">' . esc_html( $message ) . '</p>';
	}

	public static function register_block() {
		wp_register_style( 'wp-youtube', plugins_url( 'assets/player.css', WPY_FILE ), array(), WPY_VERSION );
		wp_register_style( 'wp-youtube-block-editor', plugins_url( 'assets/block-editor.css', WPY_FILE ), array(), WPY_VERSION );
		wp_register_script(
			'wp-youtube-block',
			plugins_url( 'assets/block.js', WPY_FILE ),
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			WPY_VERSION,
			true
		);
		wp_add_inline_script( 'wp-youtube-block', 'window.wpyBlock = ' . wp_json_encode( array( 'hasKey' => '' !== self::api_key(), 'maxItems' => self::MAX_ITEMS ) ) . ';', 'before' );
		register_block_type(
			'wp-youtube/playlist',
			array(
				'api_version'           => 3,
				'editor_script_handles' => array( 'wp-youtube-block' ),
				'editor_style_handles'  => array( 'wp-youtube-block-editor' ),
				'style_handles'         => array( 'wp-youtube' ),
				'attributes'            => array(
					'url'      => array( 'type' => 'string', 'default' => '' ),
					'mode'     => array( 'type' => 'string', 'default' => 'playlist' ),
					'limit'    => array( 'type' => 'number', 'default' => 12 ),
					'priority'      => array( 'type' => 'boolean', 'default' => false ),
					'listTitle'     => array( 'type' => 'string', 'default' => '' ),
					'listTitleUrl'  => array( 'type' => 'string', 'default' => '' ),
					'titlePosition' => array( 'type' => 'string', 'default' => 'below' ),
				),
				'supports'              => array( 'align' => array( 'wide', 'full' ), 'anchor' => true, 'html' => false ),
				'render_callback'       => array( __CLASS__, 'render_block' ),
			)
		);
	}

	public static function render_block( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$id = self::playlist_id( isset( $attributes['url'] ) ? $attributes['url'] : '' );
		if ( '' === $id ) {
			return self::notice( __( 'YouTube playlist: add a playlist link (with list=) or a playlist ID.', 'wp-youtube' ) );
		}
		$mode = isset( $attributes['mode'] ) && 'gallery' === $attributes['mode'] ? 'gallery' : 'playlist';
		$limit = max( 1, min( self::MAX_ITEMS, absint( isset( $attributes['limit'] ) && is_scalar( $attributes['limit'] ) ? $attributes['limit'] : 12 ) ) );
		$html = self::render( $id, $mode, $limit, ! empty( $attributes['priority'] ) );
		$title = isset( $attributes['listTitle'] ) && is_string( $attributes['listTitle'] ) ? sanitize_text_field( $attributes['listTitle'] ) : '';
		$link = isset( $attributes['listTitleUrl'] ) && is_string( $attributes['listTitleUrl'] ) ? esc_url( $attributes['listTitleUrl'] ) : '';
		$title_html = '';
		if ( '' !== $title ) {
			$title_content = '' !== $link ? '<a href="' . $link . '">' . esc_html( $title ) . '</a>' : esc_html( $title );
			$title_html = '<p class="wpy-list-title">' . $title_content . '</p>';
		}
		$above = isset( $attributes['titlePosition'] ) && 'above' === $attributes['titlePosition'];
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes( array( 'class' => 'wpy-block' ) ) : 'class="wpy-block"';
		return '<div ' . $wrapper . '>' . ( $above ? $title_html : '' ) . $html . ( $above ? '' : $title_html ) . '</div>';
	}

	public static function assets() {
		wp_enqueue_style( 'wp-youtube', plugins_url( 'assets/player.css', WPY_FILE ), array(), WPY_VERSION );
		wp_enqueue_script( 'wp-youtube', plugins_url( 'assets/player.js', WPY_FILE ), array(), WPY_VERSION, true );
	}

	/** Shortcode values pasted into the editor arrive with &amp; and sometimes curly quotes. */
	public static function clean( $value ) {
		$value = html_entity_decode( is_scalar( $value ) ? (string) $value : '', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return (string) preg_replace( '/^[\s"\'\x{201C}\x{201D}\x{201E}\x{2033}\x{00AB}\x{00BB}]+|[\s"\'\x{201C}\x{201D}\x{201E}\x{2033}\x{00AB}\x{00BB}]+$/u', '', $value );
	}

	public static function playlist_id( $value ) {
		$value = self::clean( $value );
		if ( preg_match( '/^[A-Za-z0-9_-]{12,64}$/D', $value ) ) {
			return $value;
		}
		$url = wp_parse_url( $value );
		if ( ! is_array( $url ) || ! isset( $url['host'], $url['query'] ) || ! in_array( strtolower( $url['host'] ), array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com' ), true ) ) {
			return '';
		}
		parse_str( $url['query'], $query );
		$id = isset( $query['list'] ) && is_string( $query['list'] ) ? $query['list'] : '';
		return preg_match( '/^[A-Za-z0-9_-]{12,64}$/D', $id ) ? $id : '';
	}

	public static function video_id( $value ) {
		return is_string( $value ) && preg_match( '/^[A-Za-z0-9_-]{11}$/D', $value ) ? $value : '';
	}

	private static function is_gallery_url( $url ) {
		if ( '' === self::playlist_id( $url ) ) {
			return false;
		}
		$parts = wp_parse_url( html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );
		return isset( $query['layout'] ) && is_string( $query['layout'] ) && 'gallery' === strtolower( $query['layout'] );
	}

	public static function render_embed( $html, $block ) {
		$url = isset( $block['attrs']['url'] ) ? $block['attrs']['url'] : '';
		$id = self::playlist_id( $url );
		if ( '' === $id ) {
			return $html;
		}
		$gallery = self::is_gallery_url( $url );
		return '<figure class="wp-block-embed wp-block-embed-youtube">' . self::render( $id, $gallery ? 'gallery' : 'playlist', $gallery ? 12 : 1, true ) . '</figure>';
	}

	public static function legacy_shortcode( $atts, $content = '' ) {
		$atts = is_array( $atts ) ? $atts : array();
		$url = isset( $atts['url'] ) ? $atts['url'] : $content;
		$id = self::playlist_id( $url );
		$gallery = self::is_gallery_url( $url ) || ( isset( $atts['layout'] ) && is_string( $atts['layout'] ) && 'gallery' === strtolower( $atts['layout'] ) );
		if ( '' === $id ) {
			return self::notice( __( 'YouTube: [embedyt] needs a playlist link (with list=).', 'wp-youtube' ) );
		}
		return self::render( $id, $gallery ? 'gallery' : 'playlist', $gallery ? 12 : 1, false );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'playlist' => '', 'mode' => 'gallery', 'limit' => 12, 'priority' => 'normal' ), $atts, 'wp_youtube' );
		$id = self::playlist_id( $atts['playlist'] );
		if ( '' === $id ) {
			return self::notice( __( 'YouTube: [wp_youtube] needs playlist="…" with a playlist link or ID.', 'wp-youtube' ) );
		}
		$mode = 'playlist' === strtolower( self::clean( $atts['mode'] ) ) ? 'playlist' : 'gallery';
		$limit = max( 1, min( self::MAX_ITEMS, absint( self::clean( $atts['limit'] ) ) ) );
		return self::render( $id, $mode, $limit, 'high' === strtolower( self::clean( $atts['priority'] ) ) );
	}

	private static function api_key() {
		$key = defined( 'WPY_YOUTUBE_API_KEY' ) ? WPY_YOUTUBE_API_KEY : get_option( 'wpy_youtube_api_key', '' );
		return is_string( $key ) && preg_match( '/^[A-Za-z0-9_-]{20,128}$/D', $key ) ? $key : '';
	}

	/** A stale option keeps the page usable during temporary API failures. */
	public static function videos( $playlist, $limit ) {
		$key = 'wpy_' . md5( $playlist );
		$fresh = get_transient( $key );
		if ( is_array( $fresh ) ) {
			return array_slice( $fresh, 0, $limit );
		}
		$stale = get_option( $key . '_last', array() );
		$items = array();
		$api_key = self::api_key();
		if ( '' !== $api_key ) {
			$url = add_query_arg( array( 'part' => 'snippet', 'playlistId' => $playlist, 'maxResults' => self::MAX_ITEMS, 'key' => $api_key ), 'https://www.googleapis.com/youtube/v3/playlistItems' );
			$response = wp_remote_get( $url, array( 'timeout' => 5, 'redirection' => 0, 'limit_response_size' => 200000, 'headers' => array( 'Accept' => 'application/json' ) ) );
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				foreach ( (array) ( isset( $data['items'] ) ? $data['items'] : array() ) as $item ) {
					$video = isset( $item['snippet']['resourceId']['videoId'] ) ? self::video_id( $item['snippet']['resourceId']['videoId'] ) : '';
					$title = isset( $item['snippet']['title'] ) ? sanitize_text_field( $item['snippet']['title'] ) : '';
					if ( '' !== $video && '' !== $title && 'Private video' !== $title && 'Deleted video' !== $title ) {
						$items[] = array( 'id' => $video, 'title' => $title );
					}
				}
			}
		}
		if ( ! $items && is_array( $stale ) && $stale ) {
			set_transient( $key, $stale, 300 );
			return array_slice( $stale, 0, $limit );
		}
		if ( ! $items ) {
			$response = wp_remote_get( 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( 'https://www.youtube.com/playlist?list=' . $playlist ), array( 'timeout' => 4, 'redirection' => 0, 'limit_response_size' => 10000 ) );
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				$thumb = isset( $data['thumbnail_url'] ) ? (string) $data['thumbnail_url'] : '';
				if ( preg_match( '~^https://i\.ytimg\.com/vi/([A-Za-z0-9_-]{11})/~', $thumb, $matches ) ) {
					$items[] = array( 'id' => $matches[1], 'title' => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : __( 'YouTube playlist', 'wp-youtube' ) );
				}
			}
		}
		if ( $items ) {
			set_transient( $key, $items, self::CACHE_TTL );
			update_option( $key . '_last', $items, false );
			return array_slice( $items, 0, $limit );
		}
		set_transient( $key, is_array( $stale ) ? $stale : array(), 300 );
		return array_slice( is_array( $stale ) ? $stale : array(), 0, $limit );
	}

	public static function render( $playlist, $mode, $limit, $priority ) {
		$priority = $priority && ! self::$priority_used;
		$items = self::videos( $playlist, 'gallery' === $mode ? $limit : 1 );
		if ( ! $items ) {
			return '<p>' . esc_html__( 'Videos are temporarily unavailable.', 'wp-youtube' ) . '</p>';
		}
		if ( $priority ) { self::$priority_used = true; }
		$out = '<div class="wpy-list wpy-' . esc_attr( $mode ) . '">';
		foreach ( $items as $index => $item ) {
			$video = self::video_id( isset( $item['id'] ) ? $item['id'] : '' );
			if ( '' === $video ) {
				continue;
			}
			$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
			$high = $priority && 0 === $index;
			$out .= '<div class="wpy-item"><button type="button" class="wpy-play" data-video="' . esc_attr( $video ) . '" data-playlist="' . esc_attr( $playlist ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: video title */ __( 'Play %s on YouTube', 'wp-youtube' ), $title ) ) . '">';
			$out .= '<img src="' . esc_url( self::poster_url( $video ) ) . '" width="480" height="270" alt="" loading="' . ( $high ? 'eager' : 'lazy' ) . '" decoding="async"' . ( $high ? ' fetchpriority="high"' : '' ) . '>';
			$out .= '<span class="wpy-icon" aria-hidden="true">▶</span></button>';
			if ( 'gallery' === $mode ) {
				$out .= '<p class="wpy-title">' . esc_html( $title ) . '</p>';
			}
			$out .= '</div>';
		}
		return $out . '</div>';
	}

	private static function poster_url( $video ) {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) && is_file( trailingslashit( $uploads['basedir'] ) . 'wp-youtube/' . $video . '.jpg' ) ) {
			return trailingslashit( $uploads['baseurl'] ) . 'wp-youtube/' . $video . '.jpg';
		}
		return home_url( '/?wpy_thumb=' . $video . '&wpy_sig=' . substr( hash_hmac( 'sha256', $video, wp_salt( 'auth' ) ), 0, 32 ) );
	}

	public static function thumbnail_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only image request; no login or state change.
		if ( ! isset( $_GET['wpy_thumb'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Strict video ID validation follows.
		$id = is_string( $_GET['wpy_thumb'] ) ? self::video_id( sanitize_text_field( wp_unslash( $_GET['wpy_thumb'] ) ) ) : '';
		$signature = isset( $_GET['wpy_sig'] ) && is_string( $_GET['wpy_sig'] ) ? sanitize_text_field( wp_unslash( $_GET['wpy_sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed public image URL.
		if ( '' === $id || ! hash_equals( substr( hash_hmac( 'sha256', $id, wp_salt( 'auth' ) ), 0, 32 ), $signature ) ) {
			status_header( 404 );
			exit;
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			status_header( 503 );
			exit;
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'wp-youtube';
		$file = $dir . '/' . $id . '.jpg';
		if ( ! is_file( $file ) ) {
			$response = wp_remote_get( 'https://i.ytimg.com/vi/' . $id . '/mqdefault.jpg', array( 'timeout' => 6, 'redirection' => 0, 'limit_response_size' => 310000 ) );
			$body = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
			$image_info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $body ) : false;
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) || strlen( $body ) > 300000 || strlen( $body ) < 100 || ! is_array( $image_info ) || IMAGETYPE_JPEG !== $image_info[2] ) {
				status_header( 502 );
				exit;
			}
			if ( ! wp_mkdir_p( $dir ) ) {
				status_header( 503 );
				exit;
			}
			file_put_contents( $file, $body, LOCK_EX ); // Fixed directory and strictly validated filename.
		}
		if ( ! is_file( $file ) ) {
			status_header( 503 );
			exit;
		}
		header( 'Content-Type: image/jpeg' );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $file ); // Fixed directory and strictly validated filename.
		exit;
	}

	public static function settings_menu() {
		add_options_page( 'WP YouTube', 'WP YouTube', 'manage_options', 'wp-youtube', array( __CLASS__, 'settings_page' ) );
	}

	public static function settings() {
		register_setting( 'wp_youtube', 'wpy_youtube_api_key', array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_key' ), 'default' => '' ) );
	}

	public static function sanitize_key( $key ) {
		$key = trim( (string) $key );
		return '' === $key || preg_match( '/^[A-Za-z0-9_-]{20,128}$/D', $key ) ? $key : get_option( 'wpy_youtube_api_key', '' );
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>WP YouTube</h1><p>Playlist blocks work without a key. Gallery mode needs a YouTube Data API key. Server requests use the key; visitors never receive it.</p>';
		echo '<form action="options.php" method="post">';
		settings_fields( 'wp_youtube' );
		echo '<label for="wpy-key">YouTube Data API key</label> <input id="wpy-key" name="wpy_youtube_api_key" type="password" value="' . esc_attr( get_option( 'wpy_youtube_api_key', '' ) ) . '" autocomplete="off" class="regular-text">';
		submit_button();
		echo '</form><p>In the editor, add the <strong>YouTube playlist</strong> block and paste a playlist link. The shortcode <code>[wp_youtube playlist="PLAYLIST_ID" mode="gallery" limit="12"]</code> still works; for an above-the-fold player, use <code>priority="high"</code>. A <code>WPY_YOUTUBE_API_KEY</code> constant overrides the saved setting.</p></div>';
	}
}
