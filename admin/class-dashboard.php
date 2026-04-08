<?php
/**
 * Dashboard admin page.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSP_Dashboard {

	/**
	 * Convert a UTC datetime string to WordPress local time.
	 *
	 * @param string $utc_datetime UTC datetime string (Y-m-d H:i:s).
	 * @param string $format       Output format (default: Y-m-d H:i:s).
	 * @return string Local datetime string.
	 */
	public static function to_local_time( $utc_datetime, $format = 'Y-m-d H:i:s' ) {
		$timestamp = strtotime( $utc_datetime . ' UTC' );
		if ( false === $timestamp ) {
			return $utc_datetime;
		}
		return wp_date( $format, $timestamp );
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'Site Pulse', 'site-pulse' ),
			__( 'Site Pulse', 'site-pulse' ),
			'manage_options',
			'site-pulse',
			array( __CLASS__, 'render' ),
			'dashicons-heart',
			80
		);
	}

	/**
	 * Enqueue CSS and JS on the dashboard page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_site-pulse' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wpsp-dashboard',
			WPSP_PLUGIN_URL . 'admin/css/dashboard.css',
			array(),
			WPSP_VERSION
		);

		wp_enqueue_script(
			'wpsp-chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js', // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Disclosed in readme.txt External Services section.
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_script(
			'wpsp-chartjs-adapter',
			'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js', // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Disclosed in readme.txt External Services section.
			array( 'wpsp-chartjs' ),
			'3.0.0',
			true
		);

		wp_enqueue_script(
			'wpsp-dashboard',
			WPSP_PLUGIN_URL . 'admin/js/dashboard.js',
			array( 'wpsp-chartjs', 'wpsp-chartjs-adapter' ),
			WPSP_VERSION,
			true
		);

		wp_localize_script( 'wpsp-dashboard', 'wpspData', self::get_chart_data() );
	}

	/**
	 * Prepare data for Chart.js.
	 *
	 * @return array
	 */
	private static function get_chart_data() {
		$page_checks_24h = WPSP_Page_Checker::get_checks_by_range( '24h' );
		$page_checks_7d  = WPSP_Page_Checker::get_checks_by_range( '7d' );
		$db_checks_24h   = WPSP_DB_Checker::get_checks_by_range( '24h' );
		$db_checks_7d    = WPSP_DB_Checker::get_checks_by_range( '7d' );
		$query_stats     = WPSP_DB_Checker::get_query_stats();

		$page_data_24h = self::group_page_checks( $page_checks_24h );
		$page_data_7d  = self::group_page_checks( $page_checks_7d );
		$db_data_24h   = self::group_db_checks( $db_checks_24h );
		$db_data_7d    = self::group_db_checks( $db_checks_7d );

		$page_percentiles = array();
		foreach ( $page_data_7d as $url => $points ) {
			$times = array_filter( array_column( $points, 'y' ), function ( $v ) {
				return null !== $v;
			} );
			$page_percentiles[ $url ] = array(
				'p50' => self::percentile( $times, 50 ),
				'p95' => self::percentile( $times, 95 ),
			);
		}

		$db_percentiles = array();
		foreach ( $db_data_7d as $op => $points ) {
			$times = array_filter( array_column( $points, 'y' ), function ( $v ) {
				return null !== $v;
			} );
			$db_percentiles[ $op ] = array(
				'p50' => self::percentile( $times, 50 ),
				'p95' => self::percentile( $times, 95 ),
			);
		}

		return array(
			'page24h'         => $page_data_24h,
			'page7d'          => $page_data_7d,
			'db24h'           => $db_data_24h,
			'db7d'            => $db_data_7d,
			'pagePercentiles' => $page_percentiles,
			'dbPercentiles'   => $db_percentiles,
			'queryStats'      => $query_stats,
			'thresholds'      => array(
				'page' => WPSP_PAGE_SLOW_THRESHOLD_MS,
				'db'   => WPSP_DB_SLOW_THRESHOLD_MS,
			),
			'i18n'            => array(
				'responseTime' => __( '応答時間 (ms)', 'site-pulse' ),
				'execTime'     => __( '実行時間 (ms)', 'site-pulse' ),
				'threshold'    => __( '閾値', 'site-pulse' ),
				'select'       => 'SELECT',
				'insert'       => 'INSERT',
				'update'       => 'UPDATE',
				'delete'       => 'DELETE',
			),
		);
	}

	/**
	 * Build summary from real DB data.
	 *
	 * @return array
	 */
	private static function get_summary() {
		global $wpdb;

		$table   = $wpdb->prefix . 'site_pulse_checks';
		$day_ago = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		// Page: last check per URL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_pages = $wpdb->get_results(
			"SELECT target, status_code
			 FROM {$table} AS c1
			 WHERE check_type = 'page'
			   AND created_at = (
			       SELECT MAX(created_at) FROM {$table} AS c2
			       WHERE c2.check_type = 'page' AND c2.target = c1.target
			   )
			 GROUP BY target, status_code",
			ARRAY_A
		);

		$page_total = count( $last_pages );
		$page_ok    = 0;
		foreach ( $last_pages as $r ) {
			if ( '200' === (string) $r['status_code'] ) {
				++$page_ok;
			}
		}

		// Page error rate (24h).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$page_24h = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total,
				        SUM(CASE WHEN status_code != 200 OR status_code IS NULL THEN 1 ELSE 0 END) AS errors
				 FROM {$table}
				 WHERE check_type = 'page' AND created_at >= %s",
				$day_ago
			),
			ARRAY_A
		);

		$page_error_rate = 0;
		if ( $page_24h && (int) $page_24h['total'] > 0 ) {
			$page_error_rate = round( (int) $page_24h['errors'] / (int) $page_24h['total'] * 100, 1 );
		}

		// DB: last check per type.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_dbs = $wpdb->get_results(
			"SELECT check_type, status_code
			 FROM {$table} AS c1
			 WHERE check_type LIKE 'db_%'
			   AND created_at = (
			       SELECT MAX(created_at) FROM {$table} AS c2
			       WHERE c2.check_type = c1.check_type
			   )
			 GROUP BY check_type, status_code",
			ARRAY_A
		);

		$db_total = count( $last_dbs );
		$db_ok    = 0;
		foreach ( $last_dbs as $r ) {
			if ( '1' === (string) $r['status_code'] ) {
				++$db_ok;
			}
		}

		// Slow queries count (24h).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$slow_query_24h = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}site_pulse_slow_queries WHERE created_at >= %s",
				$day_ago
			)
		);

		return array(
			'page_ok'         => $page_ok,
			'page_total'      => $page_total,
			'db_ok'           => $db_ok,
			'db_total'        => $db_total,
			'page_error_rate' => $page_error_rate,
			'slow_query_24h'  => $slow_query_24h,
		);
	}

	/**
	 * Get alert history from DB.
	 *
	 * @param int $limit Number of rows.
	 * @return array
	 */
	private static function get_alert_history( $limit = 20 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT alert_type, target, message, created_at
				 FROM {$wpdb->prefix}site_pulse_alerts
				 ORDER BY created_at DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get slow query log from DB.
	 *
	 * @param int $limit Number of rows.
	 * @return array
	 */
	private static function get_slow_query_log( $limit = 20 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query_sql, execution_time_ms, caller, created_at
				 FROM {$wpdb->prefix}site_pulse_slow_queries
				 ORDER BY created_at DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get recent page check errors (non-200 or NULL status).
	 *
	 * @param int $limit Number of rows.
	 * @return array
	 */
	private static function get_page_errors( $limit = 20 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT target, status_code, response_time_ms, created_at
				 FROM {$wpdb->prefix}site_pulse_checks
				 WHERE check_type = 'page'
				   AND (status_code IS NULL OR status_code < 200 OR status_code >= 300)
				 ORDER BY created_at DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Group page checks into { url: [ {x, y} ] } format.
	 *
	 * @param array $checks Check records.
	 * @return array
	 */
	private static function group_page_checks( $checks ) {
		$grouped = array();
		foreach ( $checks as $r ) {
			$url = $r['target'];
			if ( ! isset( $grouped[ $url ] ) ) {
				$grouped[ $url ] = array();
			}
			$grouped[ $url ][] = array(
				'x' => self::to_local_time( $r['created_at'] ),
				'y' => $r['response_time_ms'],
			);
		}
		return $grouped;
	}

	/**
	 * Group DB checks into { op: [ {x, y} ] } format.
	 *
	 * @param array $checks Check records.
	 * @return array
	 */
	private static function group_db_checks( $checks ) {
		$grouped = array();
		foreach ( $checks as $r ) {
			$op = $r['check_type'];
			if ( ! isset( $grouped[ $op ] ) ) {
				$grouped[ $op ] = array();
			}
			$grouped[ $op ][] = array(
				'x' => self::to_local_time( $r['created_at'] ),
				'y' => $r['response_time_ms'],
			);
		}
		return $grouped;
	}

	/**
	 * Calculate percentile from an array of numbers.
	 *
	 * @param array $values Numeric array.
	 * @param int   $p      Percentile (0-100).
	 * @return int|null
	 */
	private static function percentile( $values, $p ) {
		$values = array_values( $values );
		if ( empty( $values ) ) {
			return null;
		}
		sort( $values );
		$idx = (int) ceil( count( $values ) * $p / 100 ) - 1;
		return (int) $values[ max( 0, $idx ) ];
	}

	/**
	 * Render the dashboard page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$summary      = self::get_summary();
		$page_errors  = self::get_page_errors( 20 );
		$slow_queries = self::get_slow_query_log( 20 );
		$alerts       = self::get_alert_history( 20 );
		$saturation   = WPSP_DB_Checker::get_saturation();
		$query_stats  = WPSP_DB_Checker::get_query_stats();
		$urls         = WPSP_Page_Checker::get_monitored_urls();

		include WPSP_PLUGIN_DIR . 'admin/views/dashboard.php';
	}
}
