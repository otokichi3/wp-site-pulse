<?php
/**
 * Slow query detector.
 *
 * Scans $wpdb->queries (requires SAVEQUERIES) and logs queries
 * that exceed the threshold.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSP_Slow_Query {

	/**
	 * Scan $wpdb->queries and record any that exceed the threshold.
	 *
	 * Should be called at shutdown (or after a cron check cycle)
	 * so that all queries for the request have been collected.
	 */
	public static function scan() {
		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
			return;
		}

		global $wpdb;

		if ( empty( $wpdb->queries ) ) {
			return;
		}

		$threshold_ms  = (int) get_option( 'wpsp_slow_query_threshold_ms', WPSP_SLOW_QUERY_THRESHOLD_MS );
		$threshold_sec = $threshold_ms / 1000;

		foreach ( $wpdb->queries as $q ) {
			// $q = array( 0 => sql, 1 => elapsed_sec, 2 => caller_stack ).
			$sql     = $q[0];
			$elapsed = (float) $q[1];
			$caller  = isset( $q[2] ) ? $q[2] : '';

			if ( $elapsed < $threshold_sec ) {
				continue;
			}

			$time_ms = (int) round( $elapsed * 1000 );

			// Trim caller to last meaningful frame.
			$caller = self::simplify_caller( $caller );

			self::save( $sql, $time_ms, $caller );
		}
	}

	/**
	 * Save a slow query record.
	 *
	 * @param string $sql     The SQL query.
	 * @param int    $time_ms Execution time in ms.
	 * @param string $caller  Simplified caller info.
	 */
	private static function save( $sql, $time_ms, $caller ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			"{$wpdb->prefix}site_pulse_slow_queries",
			array(
				'query_sql'         => $sql,
				'execution_time_ms' => $time_ms,
				'caller'            => mb_substr( $caller, 0, 255 ),
				'created_at'        => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Extract the last meaningful caller from the stack trace string.
	 *
	 * WordPress stores callers as "func1, func2, func3".
	 * We return the last entry that isn't wpdb internals.
	 *
	 * @param string $caller_stack Comma-separated caller trace.
	 * @return string
	 */
	private static function simplify_caller( $caller_stack ) {
		if ( empty( $caller_stack ) ) {
			return '';
		}

		$parts = array_map( 'trim', explode( ',', $caller_stack ) );
		$parts = array_reverse( $parts );

		foreach ( $parts as $part ) {
			// Skip wpdb internal methods.
			if ( false !== strpos( $part, 'wpdb->' ) ) {
				continue;
			}
			return $part;
		}

		// Fallback: return the last entry.
		return end( $parts );
	}
}
