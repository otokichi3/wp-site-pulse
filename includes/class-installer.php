<?php
/**
 * Plugin installer — creates custom tables.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSP_Installer {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		update_option( 'wpsp_db_version', WPSP_VERSION );
	}

	/**
	 * Create custom tables using dbDelta.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->prefix}site_pulse_checks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			check_type varchar(20) NOT NULL,
			target varchar(255) NOT NULL,
			status_code int(11) DEFAULT NULL,
			response_time_ms int(11) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_check_type_created (check_type, created_at),
			KEY idx_target_created (target(191), created_at)
		) $charset_collate;

		CREATE TABLE {$wpdb->prefix}site_pulse_slow_queries (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			query_sql text NOT NULL,
			execution_time_ms int(11) NOT NULL,
			caller varchar(255) DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_created (created_at)
		) $charset_collate;

		CREATE TABLE {$wpdb->prefix}site_pulse_alerts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			alert_type varchar(20) NOT NULL,
			target varchar(255) NOT NULL,
			message text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_type_target_created (alert_type, target(191), created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Set default options if not present.
	 */
	private static function set_default_options() {
		if ( false === get_option( 'wpsp_monitored_urls' ) ) {
			update_option( 'wpsp_monitored_urls', array(
				home_url( '/' ),
				rest_url( '/' ),
			) );
		}
	}

	/**
	 * Drop all custom tables (called from uninstall.php).
	 */
	public static function uninstall() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}site_pulse_checks" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}site_pulse_slow_queries" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}site_pulse_alerts" );
		// phpcs:enable

		delete_option( 'wpsp_db_version' );
		delete_option( 'wpsp_monitored_urls' );
		delete_option( 'wpsp_db_saturation' );
		delete_option( 'wpsp_alert_email' );
		delete_option( 'wpsp_slow_query_threshold_ms' );
		delete_option( 'wpsp_alert_types_email' );
		delete_option( 'wpsp_auth_enabled' );
		delete_option( 'wpsp_auth_user_id' );
	}
}
