<?php
/** Update the plugin from verified GitHub release assets. */
defined( 'ABSPATH' ) || exit;

final class WP_YouTube_Updater {
	const API = 'https://api.github.com/repos/kiritoshiro/wp-youtube/releases/latest';
	const REPO = 'kiritoshiro/wp-youtube';
	const CACHE = 'wpy_latest_release';
	const INFO_SLUG = 'wp-youtube-github';

	public static function register() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'update_data' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'download' ), 10, 3 );
	}

	public static function parse( $data ) {
		if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}
		$tag = isset( $data['tag_name'] ) && is_string( $data['tag_name'] ) ? $data['tag_name'] : '';
		$version = ltrim( $tag, 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/D', $version ) ) {
			return null;
		}
		foreach ( (array) ( isset( $data['assets'] ) ? $data['assets'] : array() ) as $asset ) {
			if ( ! is_array( $asset ) || ! isset( $asset['name'] ) || 'wp-youtube-' . $version . '.zip' !== $asset['name'] || ( isset( $asset['state'] ) && 'uploaded' !== $asset['state'] ) ) {
				continue;
			}
			$url = isset( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';
			$digest = isset( $asset['digest'] ) ? $asset['digest'] : '';
			if ( ! is_string( $url ) || ! is_string( $digest ) || 0 !== strpos( $url, 'https://github.com/' . self::REPO . '/releases/download/' . rawurlencode( $tag ) . '/' ) || ! preg_match( '/^sha256:[a-f0-9]{64}$/D', $digest ) ) {
				return null;
			}
			return array( 'version' => $version, 'package' => $url, 'digest' => substr( $digest, 7 ), 'url' => 'https://github.com/' . self::REPO . '/releases/tag/' . rawurlencode( $tag ), 'published' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '' );
		}
		return null;
	}

	public static function release() {
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return ! empty( $cached['version'] ) ? $cached : null;
		}
		$response = wp_remote_get( self::API, array( 'timeout' => 8, 'redirection' => 0, 'limit_response_size' => 100000, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'wp-youtube/' . WPY_VERSION ) ) );
		$data = ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ? json_decode( wp_remote_retrieve_body( $response ), true ) : null;
		$release = self::parse( $data );
		set_site_transient( self::CACHE, $release ? $release : array( 'version' => '' ), $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $release;
	}

	public static function update_data( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( WPY_FILE ) !== $plugin_file ) {
			return $update;
		}
		$release = self::release();
		if ( ! $release ) {
			return $update;
		}
		return array( 'id' => 'github.com/' . self::REPO, 'slug' => self::INFO_SLUG, 'version' => $release['version'], 'url' => $release['url'], 'package' => $release['package'], 'requires_php' => '7.4' );
	}

	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || self::INFO_SLUG !== $args->slug ) {
			return $result;
		}
		$release = self::release();
		if ( ! $release ) {
			return $result;
		}
		return (object) array( 'name' => 'WP YouTube', 'slug' => self::INFO_SLUG, 'version' => $release['version'], 'author' => 'Adventistai', 'homepage' => 'https://github.com/' . self::REPO, 'requires' => '6.4', 'requires_php' => '7.4', 'last_updated' => $release['published'], 'download_link' => $release['package'] );
	}

	public static function download( $reply, $package, $upgrader ) {
		$release = self::release();
		if ( false !== $reply || ! $release || $release['package'] !== $package ) {
			return $reply;
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file = download_url( $package, 300 );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$digest = hash_file( 'sha256', $file );
		if ( ! is_string( $digest ) || ! hash_equals( $release['digest'], $digest ) ) {
			wp_delete_file( $file );
			return new WP_Error( 'wpy_update_digest', __( 'The GitHub update checksum did not match.', 'wp-youtube' ) );
		}
		return $file;
	}
}
