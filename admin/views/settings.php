<?php
/**
 * Settings view template.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$all_alert_types = array(
	'page_down' => __( 'ページダウン — HTTP エラーまたは接続失敗', 'site-pulse' ),
	'page_slow' => __( 'ページ応答遅延 — 応答時間が閾値超過', 'site-pulse' ),
	'db_slow'   => __( 'DB 応答遅延 — CRUD 操作が閾値超過', 'site-pulse' ),
	'db_error'  => __( 'DB エラー — テストクエリ失敗', 'site-pulse' ),
);
?>
<div class="wrap wpsp-wrap">
	<h1><?php esc_html_e( 'Site Pulse 設定', 'site-pulse' ); ?></h1>

	<?php settings_errors( 'wpsp_settings' ); ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'wpsp_save_settings', 'wpsp_settings_nonce' ); ?>

		<!-- Monitored URLs -->
		<div class="wpsp-section">
			<h3><?php esc_html_e( '監視対象 URL', 'site-pulse' ); ?></h3>
			<p class="wpsp-note">
				<?php esc_html_e( '最大10件。「認証」にチェックを入れた URL は、指定ユーザーとしてログインした状態でチェックします。会員制ページなどログインが必要なページに使用してください。', 'site-pulse' ); ?>
			</p>
			<table class="widefat" style="max-width:800px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'URL', 'site-pulse' ); ?></th>
						<th style="width:80px;text-align:center;"><?php esc_html_e( '認証', 'site-pulse' ); ?></th>
						<th style="width:40px;"></th>
					</tr>
				</thead>
				<tbody id="wpsp-url-rows">
					<?php foreach ( $settings['urls'] as $i => $url ) : ?>
					<tr>
						<td><input type="text" name="wpsp_urls[]" value="<?php echo esc_attr( $url ); ?>" class="large-text code" placeholder="https://example.com/" /></td>
						<td style="text-align:center;">
							<input type="checkbox" name="wpsp_url_auth[]" value="<?php echo esc_attr( $i ); ?>"
								<?php checked( in_array( $url, $settings['auth_urls'], true ) ); ?> />
						</td>
						<td><button type="button" class="button-link wpsp-remove-row" title="<?php esc_attr_e( '削除', 'site-pulse' ); ?>">&times;</button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<button type="button" class="button" id="wpsp-add-url" style="margin-top:8px;">+ <?php esc_html_e( 'URL を追加', 'site-pulse' ); ?></button>

			<div style="margin-top:16px;">
				<label>
					<?php esc_html_e( 'チェック実行ユーザー:', 'site-pulse' ); ?>
					<?php
					wp_dropdown_users( array(
						'name'             => 'wpsp_auth_user_id',
						'selected'         => $settings['auth_user_id'],
						'role__in'         => array( 'administrator', 'editor' ),
						'show_option_none' => __( '— 選択してください —', 'site-pulse' ),
						'option_none_value' => '0',
					) );
					?>
				</label>
				<p class="description" style="margin-top:8px;">
					<?php esc_html_e( '「認証」にチェックした URL のチェック時に、このユーザーの認証 Cookie（有効期限 60 秒）を付与します。セキュリティのため管理者・編集者のみ選択可。', 'site-pulse' ); ?>
				</p>
			</div>
		</div>

		<!-- Alert Email -->
		<div class="wpsp-section">
			<h3><?php esc_html_e( 'アラート送信先', 'site-pulse' ); ?></h3>
			<p class="wpsp-note">
				<?php esc_html_e( 'アラートメールの送信先メールアドレス。空欄の場合はサイト管理者メールが使用されます。', 'site-pulse' ); ?>
			</p>
			<input
				type="email"
				name="wpsp_alert_email"
				value="<?php echo esc_attr( $settings['alert_email'] ); ?>"
				class="regular-text"
				placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
			/>
		</div>

		<!-- Slow Query Threshold -->
		<div class="wpsp-section">
			<h3><?php esc_html_e( 'スロークエリ閾値', 'site-pulse' ); ?></h3>
			<p class="wpsp-note">
				<?php esc_html_e( 'この値（ミリ秒）を超えたクエリをスロークエリとして記録します。SAVEQUERIES が有効な場合のみ動作します。', 'site-pulse' ); ?>
			</p>
			<input
				type="number"
				name="wpsp_slow_query_threshold"
				value="<?php echo esc_attr( $settings['slow_query_threshold'] ); ?>"
				min="100"
				max="10000"
				step="50"
				class="small-text"
			/>
			<span>ms</span>
			<p class="description">
				<?php esc_html_e( '推奨: 500ms。100〜10,000ms の範囲で設定できます。', 'site-pulse' ); ?>
			</p>
		</div>

		<!-- Alert Types -->
		<div class="wpsp-section">
			<h3><?php esc_html_e( 'メール送信対象', 'site-pulse' ); ?></h3>
			<p class="wpsp-note">
				<?php esc_html_e( 'チェックしたアラート種別についてメールを送信します。', 'site-pulse' ); ?>
			</p>
			<fieldset>
				<?php foreach ( $all_alert_types as $type => $label ) : ?>
					<label style="display:block;margin-bottom:8px;">
						<input
							type="checkbox"
							name="wpsp_alert_types[]"
							value="<?php echo esc_attr( $type ); ?>"
							<?php checked( in_array( $type, $settings['alert_types'], true ) ); ?>
						/>
						<strong><?php echo esc_html( $type ); ?></strong>
						— <?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
		</div>

		<?php submit_button( __( '設定を保存', 'site-pulse' ) ); ?>
	</form>
</div>
