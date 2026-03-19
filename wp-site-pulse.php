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

// Thresholds.
define( 'WPSP_PAGE_SLOW_THRESHOLD_MS', 3000 );
define( 'WPSP_DB_SLOW_THRESHOLD_MS', 1000 );
define( 'WPSP_SLOW_QUERY_THRESHOLD_MS', 500 );
define( 'WPSP_ALERT_COOLDOWN_SEC', 3600 );
define( 'WPSP_DATA_RETENTION_DAYS', 7 );

// Load textdomain — only needed for WP < 6.7; newer versions handle this automatically.
if ( version_compare( get_bloginfo( 'version' ), '6.7', '<' ) ) {
	add_action( 'init', function () {
		load_plugin_textdomain( 'wp-site-pulse', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	} );
}

// Load classes.
require_once WPSP_PLUGIN_DIR . 'includes/class-installer.php';
require_once WPSP_PLUGIN_DIR . 'includes/class-page-checker.php';
require_once WPSP_PLUGIN_DIR . 'includes/class-db-checker.php';
require_once WPSP_PLUGIN_DIR . 'includes/class-slow-query.php';
require_once WPSP_PLUGIN_DIR . 'includes/class-alerter.php';
require_once WPSP_PLUGIN_DIR . 'includes/class-cleanup.php';
require_once WPSP_PLUGIN_DIR . 'admin/class-dashboard.php';
require_once WPSP_PLUGIN_DIR . 'admin/class-settings.php';

// Activation hook.
register_activation_hook( __FILE__, array( 'WPSP_Installer', 'activate' ) );

// Deactivation hook — remove scheduled cron events.
register_deactivation_hook( __FILE__, 'wpsp_deactivate' );

/**
 * Clear cron events on deactivation.
 */
function wpsp_deactivate() {
	wp_clear_scheduled_hook( 'wpsp_cron_check' );
	wp_clear_scheduled_hook( 'wpsp_cron_cleanup' );
}

// Register custom cron interval (15 min).
add_filter( 'cron_schedules', 'wpsp_add_cron_interval' );

/**
 * Add a 15-minute cron schedule.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function wpsp_add_cron_interval( $schedules ) {
	$schedules['wpsp_15min'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => __( '15分ごと', 'wp-site-pulse' ),
	);
	return $schedules;
}

// Schedule cron if not already scheduled.
add_action( 'init', 'wpsp_schedule_cron' );

/**
 * Ensure the cron event is scheduled.
 */
function wpsp_schedule_cron() {
	if ( ! wp_next_scheduled( 'wpsp_cron_check' ) ) {
		wp_schedule_event( time(), 'wpsp_15min', 'wpsp_cron_check' );
	}
	if ( ! wp_next_scheduled( 'wpsp_cron_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'wpsp_cron_cleanup' );
	}
}

// Cron callback — run all checks.
add_action( 'wpsp_cron_check', 'wpsp_run_checks' );

/**
 * Execute page and DB checks.
 */
function wpsp_run_checks() {
	WPSP_Page_Checker::run();
	WPSP_DB_Checker::run();
	WPSP_Slow_Query::scan();
	WPSP_Alerter::evaluate();
}

// Cleanup cron callback — daily.
add_action( 'wpsp_cron_cleanup', array( 'WPSP_Cleanup', 'run' ) );

// Boot admin dashboard.
if ( is_admin() ) {
	WPSP_Dashboard::init();
	WPSP_Settings::init();
}
