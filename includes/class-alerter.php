<?php
/**
 * Email alerter with cooldown.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSP_Alerter {

	/**
	 * Evaluate the latest check results and send alerts if needed.
	 *
	 * Called after each cron check cycle.
	 */
	public static function evaluate() {
		self::evaluate_page_checks();
		self::evaluate_db_checks();
	}

	/**
	 * Check latest page results for errors / slowness.
	 */
	private static function evaluate_page_checks() {
		global $wpdb;

		$table = $wpdb->prefix . 'site_pulse_checks';

		// Get the latest check per monitored URL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$latest = $wpdb->get_results(
			"SELECT c.target, c.status_code, c.response_time_ms
			 FROM {$table} c
			 INNER JOIN (
			     SELECT target, MAX(created_at) AS max_at
			     FROM {$table}
			     WHERE check_type = 'page'
			     GROUP BY target
			 ) t ON c.target = t.target AND c.created_at = t.max_at
			 WHERE c.check_type = 'page'",
			ARRAY_A
		);

		foreach ( $latest as $row ) {
			$url         = $row['target'];
			$status_code = $row['status_code'];
			$time_ms     = $row['response_time_ms'];

			// page_down: non-2xx or connection error.
			if ( null === $status_code || (int) $status_code < 200 || (int) $status_code >= 300 ) {
				$consecutive = self::count_consecutive_page_failures( $url );
				if ( null === $status_code ) {
					$message = sprintf(
						/* translators: %1$s: URL, %2$d: consecutive failure count */
						__( '%1$s 接続エラー（連続失敗: %2$d 回）', 'site-pulse' ),
						$url,
						$consecutive
					);
				} else {
					$message = sprintf(
						/* translators: %1$s: URL, %2$d: HTTP status code, %3$d: consecutive failure count */
						__( '%1$s が HTTP %2$d を返しました（連続失敗: %3$d 回）', 'site-pulse' ),
						$url,
						(int) $status_code,
						$consecutive
					);
				}
				self::maybe_send( 'page_down', $url, $message );
			}

			// page_slow: response time exceeds threshold.
			if ( null !== $time_ms && (int) $time_ms > WPSP_PAGE_SLOW_THRESHOLD_MS ) {
				$message = sprintf(
					/* translators: %1$s: URL, %2$d: response time in ms, %3$d: threshold in ms */
					__( '%1$s の応答時間 %2$dms（閾値: %3$dms）', 'site-pulse' ),
					$url,
					(int) $time_ms,
					WPSP_PAGE_SLOW_THRESHOLD_MS
				);
				self::maybe_send( 'page_slow', $url, $message );
			}
		}
	}

	/**
	 * Check latest DB results for errors / slowness.
	 */
	private static function evaluate_db_checks() {
		global $wpdb;

		$table = $wpdb->prefix . 'site_pulse_checks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$latest = $wpdb->get_results(
			"SELECT c.check_type, c.target, c.status_code, c.response_time_ms
			 FROM {$table} c
			 INNER JOIN (
			     SELECT check_type, MAX(created_at) AS max_at
			     FROM {$table}
			     WHERE check_type LIKE 'db_%'
			     GROUP BY check_type
			 ) t ON c.check_type = t.check_type AND c.created_at = t.max_at
			 WHERE c.check_type LIKE 'db_%'",
			ARRAY_A
		);

		$op_labels = array(
			'db_select' => 'SELECT',
			'db_insert' => 'INSERT',
			'db_update' => 'UPDATE',
			'db_delete' => 'DELETE',
		);

		foreach ( $latest as $row ) {
			$type    = $row['check_type'];
			$target  = $row['target'];
			$status  = (int) $row['status_code'];
			$time_ms = $row['response_time_ms'];
			$label   = isset( $op_labels[ $type ] ) ? $op_labels[ $type ] : $type;

			// db_error: query failed.
			if ( 0 === $status ) {
				$message = sprintf(
					/* translators: %1$s: target table, %2$s: DB operation (SELECT, INSERT, etc.) */
					__( '%1$s への DB %2$s クエリが失敗しました', 'site-pulse' ),
					$target,
					$label
				);
				self::maybe_send( 'db_error', $type, $message );
			}

			// db_slow: execution time exceeds threshold.
			if ( null !== $time_ms && (int) $time_ms > WPSP_DB_SLOW_THRESHOLD_MS ) {
				$message = sprintf(
					/* translators: %1$s: DB operation label, %2$d: execution time in ms, %3$d: threshold in ms */
					__( 'DB 操作 %1$s に %2$dms かかりました（閾値: %3$dms）', 'site-pulse' ),
					$label,
					(int) $time_ms,
					WPSP_DB_SLOW_THRESHOLD_MS
				);
				self::maybe_send( 'db_slow', $type, $message );
			}
		}
	}

	/**
	 * Alert type labels for display.
	 *
	 * @return array
	 */
	private static function get_type_labels() {
		return array(
			'page_down' => __( 'ページダウン', 'site-pulse' ),
			'page_slow' => __( 'ページ応答遅延', 'site-pulse' ),
			'db_slow'   => __( 'DB 応答遅延', 'site-pulse' ),
			'db_error'  => __( 'DB エラー', 'site-pulse' ),
		);
	}

	/**
	 * Send alert email if not in cooldown.
	 *
	 * @param string $alert_type Alert type.
	 * @param string $target     Target identifier.
	 * @param string $message    Alert message.
	 */
	private static function maybe_send( $alert_type, $target, $message ) {
		if ( self::is_in_cooldown( $alert_type, $target ) ) {
			return;
		}

		// Check if this alert type is enabled for email.
		$enabled_types = get_option( 'wpsp_alert_types_email', array( 'page_down', 'page_slow', 'db_slow', 'db_error' ) );
		if ( ! in_array( $alert_type, $enabled_types, true ) ) {
			// Still record to DB, just don't send email.
			self::record( $alert_type, $target, $message );
			return;
		}

		$to = get_option( 'wpsp_alert_email', '' );
		if ( empty( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$labels  = self::get_type_labels();
		$label   = isset( $labels[ $alert_type ] ) ? $labels[ $alert_type ] : $alert_type;
		$site    = get_bloginfo( 'name' );
		$subject = sprintf( '[%1$s] Site Pulse アラート: %2$s', $site, $label );

		$body = self::build_email_body( $alert_type, $label, $target, $message );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $to, $subject, $body, $headers );
		self::record( $alert_type, $target, $message );
	}

	/**
	 * Build a structured HTML email body.
	 *
	 * @param string $alert_type Alert type key.
	 * @param string $label      Human-readable type label.
	 * @param string $target     Target URL or operation.
	 * @param string $message    Alert detail message.
	 * @return string HTML email body.
	 */
	private static function build_email_body( $alert_type, $label, $target, $message ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$now       = current_time( 'Y-m-d H:i:s' );

		$severity_color = '#dba617';
		if ( in_array( $alert_type, array( 'page_down', 'db_error' ), true ) ) {
			$severity_color = '#d63638';
		}

		ob_start();
		?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f0f1;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f1;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">

	<!-- Header -->
	<tr>
		<td style="background:<?php echo esc_attr( $severity_color ); ?>;padding:20px 24px;">
			<span style="color:#fff;font-size:18px;font-weight:700;"><?php echo esc_html( $label ); ?></span>
		</td>
	</tr>

	<!-- Body -->
	<tr>
		<td style="padding:24px;">
			<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#1d2327;line-height:1.7;">
				<tr>
					<td style="padding:6px 0;color:#646970;width:100px;"><?php esc_html_e( 'サイト', 'site-pulse' ); ?></td>
					<td style="padding:6px 0;font-weight:600;">
						<a href="<?php echo esc_url( $site_url ); ?>" style="color:#2271b1;text-decoration:none;"><?php echo esc_html( $site_name ); ?></a>
					</td>
				</tr>
				<tr>
					<td style="padding:6px 0;color:#646970;"><?php esc_html_e( '検知日時', 'site-pulse' ); ?></td>
					<td style="padding:6px 0;"><?php echo esc_html( $now ); ?></td>
				</tr>
				<tr>
					<td style="padding:6px 0;color:#646970;"><?php esc_html_e( '種別', 'site-pulse' ); ?></td>
					<td style="padding:6px 0;">
						<span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600;background:<?php echo esc_attr( $severity_color ); ?>;color:#fff;"><?php echo esc_html( $alert_type ); ?></span>
					</td>
				</tr>
				<tr>
					<td style="padding:6px 0;color:#646970;"><?php esc_html_e( '対象', 'site-pulse' ); ?></td>
					<td style="padding:6px 0;font-family:Consolas,Monaco,monospace;font-size:13px;"><?php echo esc_html( $target ); ?></td>
				</tr>
			</table>

			<!-- Detail -->
			<div style="margin-top:16px;padding:14px 16px;background:#f6f7f7;border-radius:6px;border-left:4px solid <?php echo esc_attr( $severity_color ); ?>;font-size:14px;color:#1d2327;">
				<?php echo esc_html( $message ); ?>
			</div>

			<!-- Cooldown notice -->
			<p style="margin-top:20px;font-size:12px;color:#8c8f94;">
				<?php
				printf(
					/* translators: %d: cooldown period in minutes */
					esc_html__( '同一アラートは %d 分間抑制されます。', 'site-pulse' ),
					(int) ( WPSP_ALERT_COOLDOWN_SEC / 60 )
				);
				?>
			</p>
		</td>
	</tr>

	<!-- Footer -->
	<tr>
		<td style="padding:16px 24px;background:#f6f7f7;border-top:1px solid #dcdcde;font-size:12px;color:#8c8f94;text-align:center;">
			<?php
			printf(
				/* translators: %1$s: site name */
				esc_html__( 'このメールは %1$s の Site Pulse プラグインから自動送信されました。', 'site-pulse' ),
				esc_html( $site_name )
			);
			?>
		</td>
	</tr>

</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if the same alert_type + target was sent within cooldown period.
	 *
	 * @param string $alert_type Alert type.
	 * @param string $target     Target identifier.
	 * @return bool
	 */
	private static function is_in_cooldown( $alert_type, $target ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - WPSP_ALERT_COOLDOWN_SEC );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}site_pulse_alerts
				 WHERE alert_type = %s AND target = %s AND created_at >= %s",
				$alert_type,
				$target,
				$since
			)
		);

		return $count > 0;
	}

	/**
	 * Record an alert in the alerts table.
	 *
	 * @param string $alert_type Alert type.
	 * @param string $target     Target identifier.
	 * @param string $message    Alert message.
	 */
	private static function record( $alert_type, $target, $message ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			"{$wpdb->prefix}site_pulse_alerts",
			array(
				'alert_type' => $alert_type,
				'target'     => $target,
				'message'    => $message,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Count consecutive page failures for a URL (latest checks with non-200 status).
	 *
	 * @param string $url Target URL.
	 * @return int
	 */
	private static function count_consecutive_page_failures( $url ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$checks = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT status_code FROM {$wpdb->prefix}site_pulse_checks
				 WHERE check_type = 'page' AND target = %s
				 ORDER BY created_at DESC
				 LIMIT 20",
				$url
			)
		);

		$count = 0;
		foreach ( $checks as $code ) {
			if ( null === $code || '200' !== (string) $code ) {
				++$count;
			} else {
				break;
			}
		}

		return $count;
	}
}
