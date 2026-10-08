<?php
/* Small WordPress stubs exercise input parsing, escaped output, and release gating. */
define( 'ABSPATH', __DIR__ );
define( 'WPY_VERSION', '0.3.3' );
define( 'WPY_FILE', __DIR__ . '/../wp-youtube.php' );
define( 'HOUR_IN_SECONDS', 3600 );
function wp_parse_url( $url ) { return parse_url( $url ); }
$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['ttl'] = array();
$GLOBALS['calls'] = array();
$GLOBALS['api'] = null;
$GLOBALS['editor'] = false;
function get_transient( $key ) { return isset( $GLOBALS['transients'][ $key ] ) ? $GLOBALS['transients'][ $key ] : false; }
function get_site_transient( $key ) { global $test_release; return $test_release; }
function delete_site_transient( $key ) { $GLOBALS['deleted'][] = $key; }
function get_option( $key, $default = '' ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; $GLOBALS['ttl'][ $key ] = $ttl; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function update_option( $key, $value, $autoload ) { $GLOBALS['options'][ $key ] = $value; }
$GLOBALS['cron'] = array();
function wp_next_scheduled( $hook, $args ) { return $GLOBALS['cron'][ $hook . '|' . implode( ',', $args ) ] ?? false; }
function wp_schedule_single_event( $time, $hook, $args ) { $GLOBALS['cron'][ $hook . '|' . implode( ',', $args ) ] = $time; }
function wp_unschedule_event( $time, $hook, $args ) { unset( $GLOBALS['cron'][ $hook . '|' . implode( ',', $args ) ] ); }
function wp_remote_get( $url, $args ) {
	$GLOBALS['calls'][] = $url;
	if ( 0 === strpos( $url, 'https://www.googleapis.com/' ) ) {
		$GLOBALS['last_headers'] = $args['headers'];
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
		return call_user_func( $GLOBALS['api'], $query );
	}
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'thumbnail_url' => 'https://i.ytimg.com/vi/ABCdef12345/hqdefault.jpg', 'title' => '<script>unsafe</script> Video' ) ) );
}
function add_query_arg( $args, $url ) { return $url . '?' . implode( '&', array_map( function ( $k, $v ) { return $k . '=' . $v; }, array_keys( $args ), $args ) ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function current_user_can( $cap ) { return $GLOBALS['editor']; }
function get_block_wrapper_attributes( $extra ) { return 'class="' . $extra['class'] . '"'; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error { public $code; public function __construct( $code ) { $this->code = $code; } }
function download_url( $url, $timeout ) { $file = tempnam( sys_get_temp_dir(), 'wpy' ); file_put_contents( $file, 'tampered zip' ); return $file; }
function wp_delete_file( $file ) { unlink( $file ); }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function __( $value, $domain ) { return $value; }
function esc_html__( $value, $domain ) { return $value; }
function wp_salt( $scheme ) { return 'test-only-salt'; }
function wp_upload_dir() { return array( 'error' => '', 'basedir' => __DIR__ . '/none', 'baseurl' => 'https://example.test/uploads' ); }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function home_url( $path ) { return 'https://example.test' . $path; }
function absint( $value ) { return abs( intval( $value ) ); }
function shortcode_atts( $defaults, $atts, $name ) { return array_merge( $defaults, (array) $atts ); }
function check( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
require __DIR__ . '/../includes/class-wp-youtube.php';
require __DIR__ . '/../includes/class-updater.php';
$playlist = 'PLjcvwNsWxJC5sVZoFeEcJHNXuCeev9GvK';
check( $playlist === WP_YouTube::playlist_id( 'https://www.youtube.com/playlist?list=' . $playlist ), 'valid playlist URL' );
check( '' === WP_YouTube::playlist_id( 'https://youtube.com.evil.test/playlist?list=' . $playlist ), 'reject spoofed host' );
check( '' === WP_YouTube::video_id( '../ABCdef12345' ), 'reject path traversal' );
$html = WP_YouTube::render( $playlist, 'playlist', 1, true );
$gallery_html = WP_YouTube::render_embed( '', array( 'attrs' => array( 'url' => 'https://www.youtube.com/playlist?list=' . $playlist . '&layout=gallery' ) ) );
check( false !== strpos( $gallery_html, 'wpy-gallery' ), 'legacy gallery URL' );
check( false !== strpos( $html, '/?wpy_thumb=ABCdef12345&amp;wpy_sig=' ), 'first-party poster URL in HTML' );
check( false !== strpos( $html, 'fetchpriority="high"' ), 'priority poster' );
check( false === strpos( $html, '<script>' ) && false === strpos( $html, 'youtube.com/embed' ), 'no injected HTML or iframe before click' );
check( false === strpos( $html, 'Watch on YouTube if playback is blocked' ), 'no fixed link under videos' );
$below = WP_YouTube::render_block( array( 'url' => $playlist, 'listTitle' => 'List & title', 'listTitleUrl' => 'https://example.test/list?a=1&b=2' ) );
check( false !== strpos( $below, '<p class="wpy-list-title"><a href="https://example.test/list?a=1&amp;b=2">List &amp; title</a></p>' ), 'linked list title is escaped' );
check( strpos( $below, 'class="wpy-list wpy-playlist"' ) < strpos( $below, 'class="wpy-list-title"' ), 'list title defaults below videos' );
$above = WP_YouTube::render_block( array( 'url' => $playlist, 'listTitle' => 'Above', 'titlePosition' => 'above' ) );
check( strpos( $above, 'class="wpy-list-title"' ) < strpos( $above, 'class="wpy-list wpy-playlist"' ), 'list title can appear above videos' );
check( false === strpos( WP_YouTube::render_block( array( 'url' => $playlist ) ), 'wpy-list-title' ), 'no title when unset' );
// Pasted values: &amp; from the editor, curly quotes.
check( $playlist === WP_YouTube::playlist_id( 'https://www.youtube.com/watch?v=9REXs02evsM&amp;list=' . $playlist ), 'URL with &amp; from the editor' );
check( $playlist === WP_YouTube::playlist_id( "\u{201D}" . $playlist . "\u{201D}" ), 'curly-quoted playlist ID' );
check( 'playlist' === WP_YouTube::clean( "\u{201D}playlist\u{201D}" ), 'curly-quoted mode' );
check( '' === WP_YouTube::playlist_id( array( $playlist ) ), 'non-scalar value rejected' );

// No shortcode may swallow the content after it.
$url = 'https://www.youtube.com/playlist?list=' . $playlist;
$pair = WP_YouTube::normalize_shortcodes( '<p>[embedyt]' . $url . '[/embedyt]</p>' );
check( '<p>[embedyt url="' . $url . '"]</p>' === $pair, 'closed [embedyt] becomes self-contained' );
$unclosed = WP_YouTube::normalize_shortcodes( '<p>[embedyt]' . $url . '</p><style>.x{}</style><div>Kept</div><p>[embedyt]' . $url . '[/embedyt]</p>' );
check( false !== strpos( $unclosed, '<style>.x{}</style><div>Kept</div>' ) && false === strpos( $unclosed, '[/embedyt]' ), 'unclosed [embedyt] cannot swallow the next block' );
check( 2 === substr_count( $unclosed, '[embedyt url="' ), 'both [embedyt] keep their URL' );
$stray = WP_YouTube::normalize_shortcodes( '[wp_youtube playlist="' . $playlist . '"]<div>Kept</div>[/wp_youtube]' );
check( '[wp_youtube playlist="' . $playlist . '"]<div>Kept</div>' === $stray, 'stray [/wp_youtube] removed' );
check( 'plain text' === WP_YouTube::normalize_shortcodes( 'plain text' ), 'content without these shortcodes untouched' );
check( '' === WP_YouTube::shortcode( array( 'playlist' => 'nonsense' ) ), 'invalid shortcode prints nothing for visitors' );

$asset = array( 'name' => 'wp-youtube-0.3.0.zip', 'state' => 'uploaded', 'browser_download_url' => 'https://github.com/kiritoshiro/wp-youtube/releases/download/v0.3.0/wp-youtube-0.3.0.zip', 'digest' => 'sha256:' . str_repeat( 'a', 64 ) );
$release = array( 'tag_name' => 'v0.3.0', 'assets' => array( $asset ), 'draft' => false, 'prerelease' => false );
check( '0.3.0' === WP_YouTube_Updater::parse( $release )['version'], 'valid release' );
$release['prerelease'] = true;
check( null === WP_YouTube_Updater::parse( $release ), 'reject prerelease' );
$release['prerelease'] = false;
$release['assets'][0]['browser_download_url'] = 'https://evil.test/plugin.zip';
check( null === WP_YouTube_Updater::parse( $release ), 'reject external asset URL' );
$test_release = WP_YouTube_Updater::parse( array( 'tag_name' => 'v0.3.0', 'assets' => array( $asset ) ) );
check( is_wp_error( WP_YouTube_Updater::download( false, $test_release['package'], null ) ), 'reject tampered update ZIP' );
// "Check again" (force-check=1) drops the release cache and WordPress' plugin update data, for admins only.
$GLOBALS['deleted'] = array();
$GLOBALS['editor'] = false;
$_GET['force-check'] = '1';
WP_YouTube_Updater::force_check();
check( array() === $GLOBALS['deleted'], 'force check needs update_plugins' );
$GLOBALS['editor'] = true;
unset( $_GET['force-check'] );
WP_YouTube_Updater::force_check();
check( array() === $GLOBALS['deleted'], 'plain Updates screen keeps the cache' );
$_GET['force-check'] = '1';
WP_YouTube_Updater::force_check();
unset( $_GET['force-check'] );
check( array( 'wpy_latest_release', 'update_plugins' ) === $GLOBALS['deleted'], 'Check again refreshes the release' );
$GLOBALS['editor'] = false;
// Gallery: one key for both YouTube blocks, a large player, a grid and "Show more".
$GLOBALS['transients'] = array();
$GLOBALS['options'] = array();
check( array( '', '' ) === WP_YouTube::key_source(), 'no key' );
$GLOBALS['editor'] = true;
$nokey = WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'gallery', 'limit' => 2 ) );
check( false !== strpos( $nokey, 'needs a YouTube Data API key' ) && 1 === substr_count( $nokey, 'data-video=' ), 'gallery without a key: one video and an editor note' );
check( 1800 === WP_YouTube::CACHE_TTL && WP_YouTube::CACHE_TTL === $GLOBALS['ttl'][ 'wpy3_' . md5( $playlist . '|' ) . '_g1' ], 'without a key the oEmbed fallback is cached normally' );
$GLOBALS['options']['alps_gb_youtube_api_key'] = 'AIzaAlpsKey_0123456789abcdefgh';
check( 'AIzaAlpsKey_0123456789abcdefgh' === WP_YouTube::key_source()[0], 'falls back to the ALPS Gutenberg Blocks key' );
$GLOBALS['options']['wpy_youtube_api_key'] = 'AIzaOwnKey_0123456789abcdefghij';
check( 'AIzaOwnKey_0123456789abcdefghij' === WP_YouTube::key_source()[0], 'own key wins over the ALPS key' );
function wpy_item( $id, $title, $privacy = 'public' ) {
	return array( 'snippet' => array( 'title' => $title, 'resourceId' => array( 'videoId' => $id ) ), 'status' => array( 'privacyStatus' => $privacy ) );
}
$GLOBALS['api'] = function ( $query ) {
	if ( empty( $query['pageToken'] ) ) {
		return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'nextPageToken' => 'P2', 'items' => array( wpy_item( 'vid000000a1', 'First <b>video</b>' ), wpy_item( 'vid000000a2', 'Second & second' ), wpy_item( 'vid000000a3', 'Hidden', 'private' ) ) ) ) );
	}
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'items' => array( wpy_item( 'vid000000a4', 'Fourth' ), wpy_item( 'vid000000a5', 'Fifth' ) ) ) ) );
};
$GLOBALS['calls'] = array();
$gallery = WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'gallery', 'limit' => 2 ) );
check( 2 === count( array_filter( $GLOBALS['calls'], function ( $u ) { return false !== strpos( $u, 'playlistItems' ); } ) ), 'a new key fetches at once, both pages' );
check( 'https://example.test/' === $GLOBALS['last_headers']['Referer'], 'API requests carry the site address as Referer' );
check( false === strpos( $gallery, 'wpy-notice' ), 'no editor note when the gallery works' );
check( false !== strpos( $gallery, 'class="wpy-feature"><button type="button" class="wpy-play" data-video="vid000000a1"' ), 'first video is the large player' );
check( false !== strpos( $gallery, 'wpy_size=large&amp;wpy_sig=' ) && false !== strpos( $gallery, 'width="640" height="360"' ), 'large first-party poster for the player' );
check( 4 === substr_count( $gallery, 'class="wpy-thumb"' ) && false === strpos( $gallery, 'vid000000a3' ), 'grid lists the public videos of both pages' );
check( 2 === substr_count( $gallery, '" hidden>' ), 'videos beyond the first step are hidden' );
check( false !== strpos( $gallery, 'class="wpy-more"' ) && false !== strpos( $gallery, 'data-step="2"' ), '"Show more" button with the step size' );
check( false !== strpos( $gallery, '<a class="wpy-title" href="https://www.youtube.com/watch?v=vid000000a2&amp;list=' . $playlist . '" target="_blank" rel="noopener">Second &amp; second</a>' ), 'titles link to the video on YouTube, escaped' );
check( false !== strpos( $gallery, 'class="wpy-feature-title"><a href="https://www.youtube.com/watch?v=vid000000a1&amp;list=' ), 'player title links to YouTube' );
check( false !== strpos( $gallery, 'https://www.youtube.com/playlist?list=' . $playlist ), 'link to the whole playlist' );
check( false === strpos( $gallery, '<iframe' ) && false === strpos( $gallery, '<b>' ), 'no iframe before a click, tags stripped from titles' );
$all = WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'gallery', 'limit' => 10 ) );
check( false === strpos( $all, 'wpy-more' ) && false === strpos( $all, ' hidden>' ), 'no "Show more" when everything fits' );
check( 2 === count( array_filter( $GLOBALS['calls'], function ( $u ) { return false !== strpos( $u, 'playlistItems' ); } ) ), 'list is cached' );
$player = WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'playlist' ) );

// After 30 minutes visitors get the saved list at once and WP-Cron fetches the new one.
$listKey = 'wpy3_' . md5( $playlist . '|AIzaOwnKey_0123456789abcdefghij' ) . '_g1';
$playlistId = WP_YouTube::playlist_id( $playlist );
check( 1800 === $GLOBALS['ttl'][ $listKey ], 'a full list is fresh for 30 minutes' );
check( array( $playlistId ) === $GLOBALS['options']['wpy_playlists'], 'shown playlists are remembered for "Refresh playlists now"' );
$GLOBALS['api'] = function ( $query ) {
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'items' => array( wpy_item( 'vidnewest01', 'Newest' ), wpy_item( 'vid000000a1', 'First' ) ) ) ) );
};
unset( $GLOBALS['transients'][ $listKey ] );
$GLOBALS['calls'] = array();
$expired = WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'gallery' ) );
check( ! $GLOBALS['calls'] && false !== strpos( $expired, 'data-video="vid000000a1"' ) && false === strpos( $expired, 'vidnewest01' ), 'an expired list is shown at once, without waiting for YouTube' );
check( isset( $GLOBALS['cron'][ 'wpy_refresh_playlist|' . $playlistId ] ), 'and a background refresh is scheduled' );
WP_YouTube::refresh( $playlistId );
check( false !== strpos( WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'gallery' ) ), 'class="wpy-feature"><button type="button" class="wpy-play" data-video="vidnewest01"' ), 'after the refresh the newest video leads' );
unset( $GLOBALS['transients'][ $listKey ] );
$GLOBALS['cron'][ 'wpy_refresh_playlist|' . $playlistId ] = time() - 3600;
$GLOBALS['calls'] = array();
WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'gallery' ) );
check( 1 === count( $GLOBALS['calls'] ) && ! isset( $GLOBALS['cron'][ 'wpy_refresh_playlist|' . $playlistId ] ), 'when WP-Cron is not running the page fetches the list itself' );
$GLOBALS['calls'] = array();
check( 1 === WP_YouTube::refresh_known() && 1 === count( $GLOBALS['calls'] ), '"Refresh playlists now" fetches every shown playlist at once' );
check( 2 === $GLOBALS['options']['wpy_cache_generation'] && isset( $GLOBALS['transients'][ substr( $listKey, 0, -1 ) . '2' ] ), 'and expires every cached list' );

// A list cached by 0.3.1 (6 hours, no generation) and a playlist never
// remembered: the first view fetches it at once, it does not wait for WP-Cron.
$GLOBALS['api'] = function ( $query ) {
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'items' => array( wpy_item( 'vidtoday001', 'Today' ), wpy_item( 'vidnewest01', 'Newest' ) ) ) ) );
};
$GLOBALS['options']['wpy_playlists'] = array();
$GLOBALS['transients'] = array( substr( $listKey, 0, -3 ) => array( array( 'id' => 'vid000000a1', 'title' => 'Old' ) ) );
$GLOBALS['cron'] = array();
$GLOBALS['calls'] = array();
$upgraded = WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'playlist' ) );
check( 1 === count( $GLOBALS['calls'] ) && false !== strpos( $upgraded, 'data-video="vidtoday001"' ) && ! $GLOBALS['cron'], 'a list from an older version is replaced on the first view' );
check( array( $playlistId ) === $GLOBALS['options']['wpy_playlists'], 'the shown playlist is remembered before any fetch' );
$GLOBALS['calls'] = array();
WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'playlist' ) );
check( ! $GLOBALS['calls'], 'then it is cached again' );
check( 1 === substr_count( $player, 'data-video=' ) && false === strpos( $player, 'wpy-grid' ), 'player mode unchanged: one poster' );

// API failure with a key: one video, the reason for editors, and a quick retry.
$GLOBALS['options']['wpy_youtube_api_key'] = 'AIzaBrokenKey_0123456789abcdefgh';
$GLOBALS['api'] = function () { return array( 'response' => array( 'code' => 403 ), 'body' => '{"error":{"message":"API key not valid."}}' ); };
$broken = WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'gallery' ) );
check( false !== strpos( $broken, 'could not be read from YouTube (HTTP 403 API key not valid.)' ) && false === strpos( $broken, 'AIzaBrokenKey' ), 'editors see the API error without the key' );
check( 600 === $GLOBALS['ttl'][ 'wpy3_' . md5( $playlist . '|AIzaBrokenKey_0123456789abcdefgh' ) . '_g2' ], 'a failed API fetch is retried after 10 minutes' );
$GLOBALS['editor'] = false;
check( false === strpos( WP_YouTube::render_block( array( 'url' => $playlist, 'mode' => 'gallery' ) ), 'wpy-notice' ), 'visitors never see the note' );

// Every translatable PHP string has a Lithuanian translation.
$source = file_get_contents( __DIR__ . '/../includes/class-wp-youtube.php' );
preg_match_all( "/(?:__|esc_html__|esc_attr__)\\( '((?:[^'\\\\]|\\\\.)*)'/", $source, $m );
$translations = include __DIR__ . '/../languages/wp-youtube-lt_LT.l10n.php';
$missing = array_diff( array_map( 'stripslashes', $m[1] ), array_keys( $translations['messages'] ) );
check( ! $missing && count( $m[1] ) > 10, 'Lithuanian translation missing: ' . implode( ' | ', $missing ) );
echo "All checks passed.\n";
