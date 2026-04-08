<?php
/**
 * Plugin Name: Site Pulse
 * Plugin URI:  https://github.com/otokichi3/wp-site-pulse
 * Description: Lightweight site monitoring — page uptime, response time, DB performance, and slow query detection with a built-in dashboard.
 * Version:     0.1.0
 * Author:      otokichi3
 * Author URI:  https://github.com/otokichi3
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: site-pulse
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
	delete_transient( 'wpsp_last_check' );
	delete_transient( 'wpsp_last_cleanup' );
}

// Check interval (seconds).
define( 'WPSP_CHECK_INTERVAL', 15 * MINUTE_IN_SECONDS );
define( 'WPSP_CLEANUP_INTERVAL', DAY_IN_SECONDS );

/**
 * WP-Cron のループバック発火を無効化
 *
 * WP-Cron はページアクセス時に WordPress が自分自身へ HTTP リクエスト（ループバック）
 * を飛ばしてジョブを実行する仕組み。このリクエストが他の cron ジョブと重なると
 * ページの応答が 3〜6 秒に跳ね上がる現象が確認された。
 *
 * 代わりに shutdown フック（レスポンス送信後）でチェックを実行する。
 */
add_action( 'init', function () {
	remove_action( 'wp_loaded', 'wp_cron' );
} );

/**
 * レスポンス送信後にチェックを実行（shutdown フック）
 *
 * 前回のチェックから WPSP_CHECK_INTERVAL 秒以上経過していれば実行する。
 * transient をロック代わりに使い、重複実行を防止する。
 */
add_action( 'shutdown', 'wpsp_maybe_run_checks' );

/**
 * 前回チェックから十分な時間が経過していればチェックを実行する。
 */
function wpsp_maybe_run_checks() {
	$last_check = get_transient( 'wpsp_last_check' );
	if ( $last_check !== false ) {
		return;
	}

	// transient をセットしてロック（次の WPSP_CHECK_INTERVAL 秒間は再実行しない）。
	set_transient( 'wpsp_last_check', time(), WPSP_CHECK_INTERVAL );

	wpsp_run_checks();

	// クリーンアップも必要なら実行。
	$last_cleanup = get_transient( 'wpsp_last_cleanup' );
	if ( $last_cleanup === false ) {
		set_transient( 'wpsp_last_cleanup', time(), WPSP_CLEANUP_INTERVAL );
		WPSP_Cleanup::run();
	}
}

/**
 * Execute page and DB checks.
 */
function wpsp_run_checks() {
	WPSP_Page_Checker::run();
	WPSP_DB_Checker::run();
	WPSP_Slow_Query::scan();
	WPSP_Alerter::evaluate();
}

/**
 * 旧 WP-Cron イベントをクリーンアップ（アップデート時の互換処理）。
 */
add_action( 'init', 'wpsp_cleanup_legacy_cron' );

function wpsp_cleanup_legacy_cron() {
	if ( wp_next_scheduled( 'wpsp_cron_check' ) ) {
		wp_clear_scheduled_hook( 'wpsp_cron_check' );
	}
	if ( wp_next_scheduled( 'wpsp_cron_cleanup' ) ) {
		wp_clear_scheduled_hook( 'wpsp_cron_cleanup' );
	}
}

// Boot admin dashboard.
if ( is_admin() ) {
	WPSP_Dashboard::init();
	WPSP_Settings::init();
}
