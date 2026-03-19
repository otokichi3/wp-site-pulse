<?php
/**
 * Page uptime & response time checker.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSP_Page_Checker {

	/**
	 * Run checks for all monitored URLs.
	 */
	public static function run() {
		$urls = self::get_monitored_urls();

		foreach ( $urls as $url ) {
			self::check_url( $url );
		}
	}

	/**
	 * Check a single URL and save the result.
	 *
	 * @param string $url URL to check.
	 */
	private static function check_url( $url ) {
		$cookies = self::get_auth_cookies( $url );

		$request_url = self::resolve_internal_url( $url );
		$host_header = self::get_original_host( $url );

		$args = array(
			'timeout'     => 15,
			'redirection' => 3,
			'sslverify'   => false,
		);
		if ( ! empty( $cookies ) ) {
			$args['cookies'] = $cookies;
		}
		if ( $host_header ) {
			$args['headers'] = array( 'Host' => $host_header );
		}

		$start    = microtime( true );
		$response = wp_remote_get( $request_url, $args );
		$time_ms  = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			self::save( $url, null, null );
		} else {
			$status_code = wp_remote_retrieve_response_code( $response );
			self::save( $url, $status_code, $time_ms );
		}
	}

	/**
	 * Save a page check result.
	 *
	 * @param string   $url         Checked URL.
	 * @param int|null $status_code HTTP status code, null on connection error.
	 * @param int|null $time_ms     Response time in ms, null on connection error.
	 */
	private static function save( $url, $status_code, $time_ms ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			"{$wpdb->prefix}site_pulse_checks",
			array(
				'check_type'       => 'page',
				'target'           => $url,
				'status_code'      => $status_code,
				'response_time_ms' => $time_ms,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Extract the Host header value from the original URL.
	 *
	 * When we rewrite localhost:8080 to 127.0.0.1:80, WordPress sees a
	 * different host and issues canonical redirects. Sending the original
	 * Host header prevents this.
	 *
	 * @param string $url Original URL.
	 * @return string|null Host header value, or null if not needed.
	 */
	private static function get_original_host( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) ) {
			return null;
		}

		$host = $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$host .= ':' . $parts['port'];
		}

		return $host;
	}

	/**
	 * Convert an external-facing URL to one reachable from within the server.
	 *
	 * In containerised environments (e.g. Docker) the published port
	 * (e.g. localhost:8080) is not reachable from inside the container
	 * where WordPress listens on port 80.  This method rewrites the URL
	 * to hit 127.0.0.1:80 so wp_remote_get works from cron / CLI.
	 *
	 * @param string $url The original URL.
	 * @return string     The URL rewritten for internal access.
	 */
	private static function resolve_internal_url( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) ) {
			return $url;
		}

		// If the host is localhost / 127.0.0.1, strip the published port
		// so the request hits port 80 (Apache/nginx inside the container).
		$local_hosts = array( 'localhost', '127.0.0.1', '::1' );
		if ( in_array( $parts['host'], $local_hosts, true ) ) {
			$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'http';
			$path   = isset( $parts['path'] ) ? $parts['path'] : '/';
			$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
			return $scheme . '://127.0.0.1' . $path . $query;
		}

		// Same-site check: if the URL host matches site_url host,
		// treat it as local and strip the port.
		$site_parts = wp_parse_url( site_url() );
		if ( ! empty( $site_parts['host'] ) && $parts['host'] === $site_parts['host'] ) {
			$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'http';
			$path   = isset( $parts['path'] ) ? $parts['path'] : '/';
			$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
			return $scheme . '://127.0.0.1' . $path . $query;
		}

		return $url;
	}

	/**
	 * Generate WP auth cookies for authenticated monitoring.
	 *
	 * Returns an array of WP_Http_Cookie objects if enabled,
	 * or an empty array if disabled.
	 *
	 * @param string $url The URL being checked (used for cookie domain/path).
	 * @return array Array of WP_Http_Cookie objects.
	 */
	private static function get_auth_cookies( $url ) {
		if ( ! get_option( 'wpsp_auth_enabled', false ) ) {
			return array();
		}

		$user_id = (int) get_option( 'wpsp_auth_user_id', 0 );
		if ( $user_id <= 0 ) {
			return array();
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		$expiration = time() + 60; // 60 seconds — just enough for this request.
		$scheme     = wp_parse_url( $url, PHP_URL_SCHEME );
		$secure     = ( 'https' === $scheme );

		// Generate cookie values using WP core functions.
		$auth_cookie       = wp_generate_auth_cookie( $user_id, $expiration, 'auth' );
		$logged_in_cookie  = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in' );

		// Build cookies without domain/path constraints.
		// wp_remote_get sends cookies regardless of domain when
		// domain is omitted, which avoids localhost/127.0.0.1 mismatch.
		$cookies = array();

		$cookies[] = new WP_Http_Cookie( array(
			'name'  => defined( 'AUTH_COOKIE' ) ? AUTH_COOKIE : 'wordpress_' . md5( site_url() ),
			'value' => $auth_cookie,
		) );

		$cookies[] = new WP_Http_Cookie( array(
			'name'  => defined( 'LOGGED_IN_COOKIE' ) ? LOGGED_IN_COOKIE : 'wordpress_logged_in_' . md5( site_url() ),
			'value' => $logged_in_cookie,
		) );

		if ( $secure ) {
			$secure_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'secure_auth' );
			$cookies[]     = new WP_Http_Cookie( array(
				'name'  => defined( 'SECURE_AUTH_COOKIE' ) ? SECURE_AUTH_COOKIE : 'wordpress_sec_' . md5( site_url() ),
				'value' => $secure_cookie,
			) );
		}

		return $cookies;
	}

	/*
	 * -------------------------------------------------------
	 * Read methods (used by Dashboard)
	 * -------------------------------------------------------
	 */

	/**
	 * Get monitored URLs from options.
	 *
	 * @return array
	 */
	public static function get_monitored_urls() {
		$urls = get_option( 'wpsp_monitored_urls' );

		if ( ! is_array( $urls ) || empty( $urls ) ) {
			return array(
				home_url( '/' ),
				rest_url( '/' ),
			);
		}

		return $urls;
	}

	/**
	 * Get page check records filtered by time range.
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
				 WHERE check_type = 'page' AND created_at >= %s
				 ORDER BY created_at ASC",
				$since
			),
			ARRAY_A
		);
	}
}
