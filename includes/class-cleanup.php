<?php
/**
 * Data cleanup — enforces retention policies.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSP_Cleanup {

	/**
	 * Max rows to keep in slow_queries and alerts tables.
	 */
	const MAX_ROWS = 100;

	/**
	 * Run all cleanup tasks.
	 */
	public static function run() {
		self::purge_old_checks();
		self::trim_slow_queries();
		self::trim_alerts();
	}

	/**
	 * Delete checks older than WPSP_DATA_RETENTION_DAYS.
	 */
	private static function purge_old_checks() {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( WPSP_DATA_RETENTION_DAYS * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}site_pulse_checks WHERE created_at < %s",
				$cutoff
			)
		);
	}

	/**
	 * Keep only the latest MAX_ROWS in slow_queries.
	 */
	private static function trim_slow_queries() {
		global $wpdb;

		$table = "{$wpdb->prefix}site_pulse_slow_queries";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( $count <= self::MAX_ROWS ) {
			return;
		}

		// Get the id threshold — keep rows with id >= this.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$min_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d",
				self::MAX_ROWS - 1
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id < %d",
				$min_id
			)
		);
	}

	/**
	 * Keep only the latest MAX_ROWS in alerts.
	 */
	private static function trim_alerts() {
		global $wpdb;

		$table = "{$wpdb->prefix}site_pulse_alerts";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( $count <= self::MAX_ROWS ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$min_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d",
				self::MAX_ROWS - 1
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id < %d",
				$min_id
			)
		);
	}
}
