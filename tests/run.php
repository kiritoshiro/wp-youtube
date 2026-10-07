<?php
/* Small WordPress stubs exercise input parsing, escaped output, and release gating. */
define( 'ABSPATH', __DIR__ );
define( 'WPY_VERSION', '0.2.1' );
define( 'WPY_FILE', __DIR__ . '/../wp-youtube.php' );
define( 'HOUR_IN_SECONDS', 3600 );
function wp_parse_url( $url ) { return parse_url( $url ); }
function get_transient( $key ) { return false; }
function get_site_transient( $key ) { global $test_release; return $test_release; }
function get_option( $key, $default = '' ) { return $default; }
function set_transient( $key, $value, $ttl ) {}
function update_option( $key, $value, $autoload ) {}
function wp_remote_get( $url, $args ) { return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'thumbnail_url' => 'https://i.ytimg.com/vi/ABCdef12345/hqdefault.jpg', 'title' => '<script>unsafe</script> Video' ) ) ); }
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
check( false !== strpos( $html, 'https://www.youtube.com/watch?v=ABCdef12345&amp;list=' . $playlist ), 'direct YouTube fallback URL' );
check( false !== strpos( $html, 'rel="noopener noreferrer"' ), 'safe external fallback link' );
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

$asset = array( 'name' => 'wp-youtube-0.2.1.zip', 'state' => 'uploaded', 'browser_download_url' => 'https://github.com/kiritoshiro/wp-youtube/releases/download/v0.2.1/wp-youtube-0.2.1.zip', 'digest' => 'sha256:' . str_repeat( 'a', 64 ) );
$release = array( 'tag_name' => 'v0.2.1', 'assets' => array( $asset ), 'draft' => false, 'prerelease' => false );
check( '0.2.1' === WP_YouTube_Updater::parse( $release )['version'], 'valid release' );
$release['prerelease'] = true;
check( null === WP_YouTube_Updater::parse( $release ), 'reject prerelease' );
$release['prerelease'] = false;
$release['assets'][0]['browser_download_url'] = 'https://evil.test/plugin.zip';
check( null === WP_YouTube_Updater::parse( $release ), 'reject external asset URL' );
$test_release = WP_YouTube_Updater::parse( array( 'tag_name' => 'v0.2.1', 'assets' => array( $asset ) ) );
check( is_wp_error( WP_YouTube_Updater::download( false, $test_release['package'], null ) ), 'reject tampered update ZIP' );
echo "All checks passed.\n";
