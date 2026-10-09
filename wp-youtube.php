<?php
/**
 * Plugin Name: WP YouTube
 * Plugin URI: https://github.com/kiritoshiro/wp-youtube
 * Description: Fast, privacy-conscious YouTube playlists and galleries.
 * Version: 0.3.5
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Adventistai
 * License: GPL-3.0-or-later
 * Update URI: https://github.com/kiritoshiro/wp-youtube
 * Text Domain: wp-youtube
 */

defined( 'ABSPATH' ) || exit;

define( 'WPY_VERSION', '0.3.5' );
define( 'WPY_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-wp-youtube.php';
require_once __DIR__ . '/includes/class-updater.php';

WP_YouTube::register();
WP_YouTube_Updater::register();
