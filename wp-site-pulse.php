<?php
/**
 * Plugin Name: WP Site Pulse
 * Plugin URI:  https://github.com/otokichi3/wp-site-pulse
 * Description: Lightweight site monitoring — page uptime, response time, DB performance, and slow query detection with a built-in dashboard.
 * Version:     0.1.0
 * Author:      otokichi3
 * Author URI:  https://github.com/otokichi3
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-site-pulse
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPSP_VERSION', '0.1.0' );
define( 'WPSP_PLUGIN_FILE', __FILE__ );
define( 'WPSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
