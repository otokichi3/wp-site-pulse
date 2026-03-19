<?php
/**
 * DB performance checker.
 *
 * Runs CRUD test queries and records execution times.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSP_DB_Checker {

	/**
	 * Temporary option key used for INSERT/UPDATE/DELETE tests.
	 */
	const TEST_OPTION_KEY = '_wpsp_db_test_temp';

	/**
	 * Run all CRUD tests and save results to the checks table.
	 */
	public static function run() {
		self::test_select();
		self::test_insert();
		self::test_update();
		self::test_delete();
	}

	/**
	 * SELECT test — fetch the latest post from wp_posts.
	 */
	private static function test_select() {
		global $wpdb;

		$start = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' ORDER BY post_date DESC LIMIT 1"
		);
		$time = self::elapsed_ms( $start );

		$success = ( '' === $wpdb->last_error );
		self::save( 'db_select', 'wp_posts', $success, $time );
	}

	/**
	 * INSERT test — insert a temporary row into wp_options.
	 */
	private static function test_insert() {
		global $wpdb;

		// Clean up any stale test row first.
		delete_option( self::TEST_OPTION_KEY );

		$start = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => self::TEST_OPTION_KEY,
				'option_value' => wp_generate_uuid4(),
				'autoload'     => 'no',
			),
			array( '%s', '%s', '%s' )
		);
		$time = self::elapsed_ms( $start );

		self::save( 'db_insert', 'wp_options', false !== $result, $time );
	}

	/**
	 * UPDATE test — update the temporary row.
	 */
	private static function test_update() {
		global $wpdb;

		$start = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$wpdb->options,
			array( 'option_value' => wp_generate_uuid4() ),
			array( 'option_name' => self::TEST_OPTION_KEY ),
			array( '%s' ),
			array( '%s' )
		);
		$time = self::elapsed_ms( $start );

		// $result is false on error, 0 if no rows matched (shouldn't happen).
		self::save( 'db_update', 'wp_options', false !== $result, $time );
	}

	/**
	 * DELETE test — remove the temporary row.
	 */
	private static function test_delete() {
		global $wpdb;

		$start = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$wpdb->options,
			array( 'option_name' => self::TEST_OPTION_KEY ),
			array( '%s' )
		);
		$time = self::elapsed_ms( $start );

		self::save( 'db_delete', 'wp_options', false !== $result, $time );
	}

	/**
	 * Save a check result to the checks table.
	 *
	 * @param string   $check_type  db_select|db_insert|db_update|db_delete.
	 * @param string   $target      Table name.
	 * @param bool     $success     Whether the operation succeeded.
	 * @param int|null $time_ms     Execution time in ms, null on error.
	 */
	private static function save( $check_type, $target, $success, $time_ms ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			"{$wpdb->prefix}site_pulse_checks",
			array(
				'check_type'       => $check_type,
				'target'           => $target,
				'status_code'      => $success ? 1 : 0,
				'response_time_ms' => $success ? $time_ms : null,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Calculate elapsed time in milliseconds.
	 *
	 * @param float $start microtime(true) value.
	 * @return int
	 */
	private static function elapsed_ms( $start ) {
		return (int) round( ( microtime( true ) - $start ) * 1000 );
	}

	/*
	 * -------------------------------------------------------
	 * Read methods (used by Dashboard)
	 * -------------------------------------------------------
	 */

	/**
	 * Get DB check records filtered by time range.
	 *
	 * @param string $range '24h' or '7d'.
	 * @return array
	 */
	public static function get_checks_by_range( $range = '24h' ) {
		global $wpdb;

		$seconds = ( '7d' === $range ) ? 7 * DAY_IN_SECONDS : DAY_IN_SECONDS;
		$since   = gmdate( 'Y-m-d H:i:s', time() - $seconds );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT check_type, target, status_code, response_time_ms, created_at
				 FROM {$wpdb->prefix}site_pulse_checks
				 WHERE check_type LIKE %s AND created_at >= %s
				 ORDER BY created_at ASC",
				'db_%',
				$since
			),
			ARRAY_A
		);
	}

	/**
	 * Get DB saturation data (live query).
	 *
	 * @return array
	 */
	public static function get_saturation() {
		global $wpdb;

		// Autoload size — WP 6.6+ uses 'on'/'auto' instead of 'yes'.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$autoload_size = (int) $wpdb->get_var(
			"SELECT ROUND(SUM(LENGTH(option_value)) / 1024)
			 FROM {$wpdb->options}
			 WHERE autoload IN ('yes','on','auto')"
		);

		// Autoload count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$autoload_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto')"
		);

		// Expired transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired_transients = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		// Revisions count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$revisions = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
		);

		// Published posts count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type NOT IN ('revision','nav_menu_item','custom_css','customize_changeset','oembed_cache','wp_global_styles','wp_navigation','wp_template','wp_template_part','wp_font_family','wp_font_face')
			 AND post_status IN ('publish','draft','private','pending','future')"
		);

		return array(
			'autoload_size_kb'   => $autoload_size,
			'autoload_count'     => $autoload_count,
			'expired_transients' => $expired_transients,
			'revisions'          => $revisions,
			'posts'              => max( 1, $posts ),
		);
	}

	/**
	 * Get query statistics from SAVEQUERIES.
	 *
	 * @return array
	 */
	public static function get_query_stats() {
		global $wpdb;

		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES || empty( $wpdb->queries ) ) {
			return array(
				'total'  => 0,
				'select' => 0,
				'insert' => 0,
				'update' => 0,
				'delete' => 0,
			);
		}

		$stats = array(
			'total'  => count( $wpdb->queries ),
			'select' => 0,
			'insert' => 0,
			'update' => 0,
			'delete' => 0,
		);

		foreach ( $wpdb->queries as $q ) {
			$sql   = strtoupper( ltrim( $q[0] ) );
			$first = strtok( $sql, ' ' );
			switch ( $first ) {
				case 'SELECT':
					++$stats['select'];
					break;
				case 'INSERT':
					++$stats['insert'];
					break;
				case 'UPDATE':
					++$stats['update'];
					break;
				case 'DELETE':
					++$stats['delete'];
					break;
			}
		}

		return $stats;
	}
}
