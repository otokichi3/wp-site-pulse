<?php
/**
 * Settings page.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSP_Settings {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_submenu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Add submenu under Site Pulse.
	 */
	public static function add_submenu() {
		add_submenu_page(
			'site-pulse',
			__( '設定', 'site-pulse' ),
			__( '設定', 'site-pulse' ),
			'manage_options',
			'wpsp-settings',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Enqueue settings page CSS (reuse dashboard CSS).
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'site-pulse_page_wpsp-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wpsp-dashboard',
			WPSP_PLUGIN_URL . 'admin/css/dashboard.css',
			array(),
			WPSP_VERSION
		);

		wp_enqueue_script(
			'wpsp-settings',
			WPSP_PLUGIN_URL . 'admin/js/settings.js',
			array(),
			WPSP_VERSION,
			true
		);
	}

	/**
	 * Handle form submission.
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['wpsp_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsp_settings_nonce'] ) ), 'wpsp_save_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Monitored URLs + per-URL auth flags.
		$raw_urls  = isset( $_POST['wpsp_urls'] ) && is_array( $_POST['wpsp_urls'] ) ? wp_unslash( $_POST['wpsp_urls'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$raw_auths = isset( $_POST['wpsp_url_auth'] ) && is_array( $_POST['wpsp_url_auth'] ) ? array_map( 'absint', wp_unslash( $_POST['wpsp_url_auth'] ) ) : array();

		$urls      = array();
		$auth_urls = array();

		foreach ( $raw_urls as $i => $raw_url ) {
			$url = esc_url_raw( trim( sanitize_text_field( $raw_url ) ) );
			if ( empty( $url ) ) {
				continue;
			}
			$urls[] = $url;
			if ( in_array( $i, $raw_auths, true ) ) {
				$auth_urls[] = $url;
			}
		}

		$urls      = array_slice( $urls, 0, 10 );
		$auth_urls = array_intersect( $auth_urls, $urls );
		update_option( 'wpsp_monitored_urls', array_values( $urls ) );
		update_option( 'wpsp_auth_urls', array_values( $auth_urls ) );

		// Auth user for per-URL authenticated checks.
		$auth_user_id = isset( $_POST['wpsp_auth_user_id'] ) ? absint( wp_unslash( $_POST['wpsp_auth_user_id'] ) ) : 0;
		if ( $auth_user_id > 0 ) {
			$user = get_userdata( $auth_user_id );
			// Only allow administrators and editors.
			if ( $user && ( in_array( 'administrator', $user->roles, true ) || in_array( 'editor', $user->roles, true ) ) ) {
				update_option( 'wpsp_auth_user_id', $auth_user_id );
			}
		} else {
			update_option( 'wpsp_auth_user_id', 0 );
		}

		// Alert email.
		$email = isset( $_POST['wpsp_alert_email'] ) ? sanitize_email( wp_unslash( $_POST['wpsp_alert_email'] ) ) : '';
		update_option( 'wpsp_alert_email', $email );

		// Slow query threshold.
		$threshold = isset( $_POST['wpsp_slow_query_threshold'] ) ? absint( wp_unslash( $_POST['wpsp_slow_query_threshold'] ) ) : 500;
		$threshold = max( 100, min( 10000, $threshold ) );
		update_option( 'wpsp_slow_query_threshold_ms', $threshold );

		// Alert types for email.
		$all_types    = array( 'page_down', 'page_slow', 'db_slow', 'db_error' );
		$active_types = array();
		if ( isset( $_POST['wpsp_alert_types'] ) && is_array( $_POST['wpsp_alert_types'] ) ) {
			foreach ( array_map( 'sanitize_text_field', wp_unslash( $_POST['wpsp_alert_types'] ) ) as $type ) {
				if ( in_array( $type, $all_types, true ) ) {
					$active_types[] = $type;
				}
			}
		}
		update_option( 'wpsp_alert_types_email', $active_types );

		add_settings_error( 'wpsp_settings', 'wpsp_saved', __( '設定を保存しました。', 'site-pulse' ), 'success' );
	}

	/**
	 * Get current settings with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$urls = get_option( 'wpsp_monitored_urls' );
		if ( ! is_array( $urls ) || empty( $urls ) ) {
			$urls = array( home_url( '/' ), rest_url( '/' ) );
		}

		return array(
			'urls'                   => $urls,
			'auth_user_id'           => (int) get_option( 'wpsp_auth_user_id', 0 ),
			'auth_urls'              => (array) get_option( 'wpsp_auth_urls', array() ),
			'alert_email'            => get_option( 'wpsp_alert_email', get_option( 'admin_email' ) ),
			'slow_query_threshold'   => (int) get_option( 'wpsp_slow_query_threshold_ms', WPSP_SLOW_QUERY_THRESHOLD_MS ),
			'alert_types'            => get_option( 'wpsp_alert_types_email', array( 'page_down', 'page_slow', 'db_slow', 'db_error' ) ),
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();

		include WPSP_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
