<?php
/**
 * Dashboard view template.
 *
 * @package WP_Site_Pulse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ---------- Status computation ----------
 */

// Page health.
$page_is_green  = ( $summary['page_ok'] === $summary['page_total'] );
$page_is_yellow = ( ! $page_is_green && $summary['page_ok'] > 0 );
$page_is_red    = ( 0 === $summary['page_ok'] );

$page_status = $page_is_green ? 'green' : ( $page_is_yellow ? 'yellow' : 'red' );

$error_status = 'green';
if ( $summary['page_error_rate'] > 5 ) {
	$error_status = 'red';
} elseif ( $summary['page_error_rate'] > 0 ) {
	$error_status = 'yellow';
}
// Page overall: worst of page_status and error_status.
$page_overall = 'green';
if ( 'red' === $page_status || 'red' === $error_status ) {
	$page_overall = 'red';
} elseif ( 'yellow' === $page_status || 'yellow' === $error_status ) {
	$page_overall = 'yellow';
}

// DB health.
$db_is_green  = ( $summary['db_ok'] === $summary['db_total'] );
$db_is_yellow = ( ! $db_is_green && $summary['db_ok'] > 0 );

$db_status = $db_is_green ? 'green' : ( $db_is_yellow ? 'yellow' : 'red' );

$slow_status = 'green';
if ( $summary['slow_query_24h'] > 5 ) {
	$slow_status = 'red';
} elseif ( $summary['slow_query_24h'] >= 1 ) {
	$slow_status = 'yellow';
}
// DB overall: worst of db_status and slow_status.
$db_overall = 'green';
if ( 'red' === $db_status || 'red' === $slow_status ) {
	$db_overall = 'red';
} elseif ( 'yellow' === $db_status || 'yellow' === $slow_status ) {
	$db_overall = 'yellow';
}

// Site-wide overall: worst of page and db.
$site_overall = 'green';
if ( 'red' === $page_overall || 'red' === $db_overall ) {
	$site_overall = 'red';
} elseif ( 'yellow' === $page_overall || 'yellow' === $db_overall ) {
	$site_overall = 'yellow';
}

$status_labels = array(
	'green'  => __( '正常', 'site-pulse' ),
	'yellow' => __( '注意', 'site-pulse' ),
	'red'    => __( '異常', 'site-pulse' ),
);
$status_icons = array(
	'green'  => 'dashicons-yes-alt',
	'yellow' => 'dashicons-warning',
	'red'    => 'dashicons-dismiss',
);

/*
 * ---------- Saturation computation ----------
 */
$al_size  = $saturation['autoload_size_kb'];
$al_class = 'wpsp-gauge--green';
$al_pct   = min( 100, round( $al_size / 2000 * 100 ) );
if ( $al_size > 1500 ) {
	$al_class = 'wpsp-gauge--red';
} elseif ( $al_size > 800 ) {
	$al_class = 'wpsp-gauge--yellow';
}

$al_cnt       = $saturation['autoload_count'];
$al_cnt_class = 'wpsp-gauge--green';
$al_cnt_pct   = min( 100, round( $al_cnt / 1500 * 100 ) );
if ( $al_cnt > 1000 ) {
	$al_cnt_class = 'wpsp-gauge--red';
} elseif ( $al_cnt > 500 ) {
	$al_cnt_class = 'wpsp-gauge--yellow';
}

$exp       = $saturation['expired_transients'];
$exp_class = 'wpsp-gauge--green';
$exp_pct   = min( 100, round( $exp / 200 * 100 ) );
if ( $exp > 50 ) {
	$exp_class = 'wpsp-gauge--red';
} elseif ( $exp > 0 ) {
	$exp_class = 'wpsp-gauge--yellow';
}

$rev_count  = $saturation['revisions'];
$post_count = max( 1, $saturation['posts'] );
$rev_ratio  = round( $rev_count / $post_count, 1 );
$rev_class  = 'wpsp-gauge--green';
$rev_pct    = min( 100, round( $rev_ratio / 30 * 100 ) );
if ( $rev_ratio > 20 ) {
	$rev_class = 'wpsp-gauge--red';
} elseif ( $rev_ratio > 10 ) {
	$rev_class = 'wpsp-gauge--yellow';
}
?>
<div class="wrap wpsp-wrap">
	<h1><?php esc_html_e( 'Site Pulse ダッシュボード', 'site-pulse' ); ?></h1>

	<!-- ===== Level 1: Site Overall ===== -->
	<div class="wpsp-hero wpsp-hero--<?php echo esc_attr( $site_overall ); ?>">
		<div class="wpsp-hero__indicator">
			<span class="wpsp-hero__dot wpsp-hero__dot--<?php echo esc_attr( $site_overall ); ?>"></span>
		</div>
	</div>

	<!-- ===== Level 2: Page / DB pillars ===== -->
	<div class="wpsp-pillars">
		<!-- Page pillar -->
		<div class="wpsp-pillar wpsp-pillar--<?php echo esc_attr( $page_overall ); ?>">
			<div class="wpsp-pillar__header">
				<span class="wpsp-pillar__dot wpsp-pillar__dot--<?php echo esc_attr( $page_overall ); ?>"></span>
				<span class="wpsp-pillar__title"><?php esc_html_e( 'ページ監視', 'site-pulse' ); ?></span>
			</div>
			<div class="wpsp-pillar__metrics">
				<div class="wpsp-metric">
					<span class="wpsp-metric__label"><?php esc_html_e( '稼働状況', 'site-pulse' ); ?></span>
					<span class="wpsp-metric__value wpsp-metric__value--<?php echo esc_attr( $page_status ); ?>">
						<?php
						printf(
							/* translators: %1$d: number of OK pages, %2$d: total pages */
							esc_html__( '%1$d / %2$d 正常', 'site-pulse' ),
							intval( $summary['page_ok'] ),
							intval( $summary['page_total'] )
						);
						?>
					</span>
				</div>
				<div class="wpsp-metric">
					<span class="wpsp-metric__label"><?php esc_html_e( 'エラー率 (24h)', 'site-pulse' ); ?></span>
					<span class="wpsp-metric__value wpsp-metric__value--<?php echo esc_attr( $error_status ); ?>">
						<?php echo esc_html( $summary['page_error_rate'] . '%' ); ?>
					</span>
				</div>
			</div>
		</div>

		<!-- DB pillar -->
		<div class="wpsp-pillar wpsp-pillar--<?php echo esc_attr( $db_overall ); ?>">
			<div class="wpsp-pillar__header">
				<span class="wpsp-pillar__dot wpsp-pillar__dot--<?php echo esc_attr( $db_overall ); ?>"></span>
				<span class="wpsp-pillar__title"><?php esc_html_e( 'データベース', 'site-pulse' ); ?></span>
			</div>
			<div class="wpsp-pillar__metrics">
				<div class="wpsp-metric">
					<span class="wpsp-metric__label"><?php esc_html_e( 'CRUD テスト', 'site-pulse' ); ?></span>
					<span class="wpsp-metric__value wpsp-metric__value--<?php echo esc_attr( $db_status ); ?>">
						<?php
						printf(
							/* translators: %1$d: number of OK DB operations, %2$d: total DB operations */
							esc_html__( '%1$d / %2$d 正常', 'site-pulse' ),
							intval( $summary['db_ok'] ),
							intval( $summary['db_total'] )
						);
						?>
					</span>
				</div>
				<div class="wpsp-metric">
					<span class="wpsp-metric__label"><?php esc_html_e( 'スロークエリ (24h)', 'site-pulse' ); ?></span>
					<span class="wpsp-metric__value wpsp-metric__value--<?php echo esc_attr( $slow_status ); ?>">
						<?php
						printf(
							/* translators: %d: number of slow queries detected */
							esc_html__( '%d 件検出', 'site-pulse' ),
							intval( $summary['slow_query_24h'] )
						);
						?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== Level 3: Details — Page ===== -->
	<h2 class="wpsp-group-heading"><?php esc_html_e( 'ページ監視 — 詳細', 'site-pulse' ); ?></h2>

	<div class="wpsp-section">
		<div class="wpsp-section__header">
			<h3><?php esc_html_e( '応答時間', 'site-pulse' ); ?></h3>
			<div class="wpsp-range-toggle" data-chart="page">
				<button type="button" class="wpsp-range-btn wpsp-range-btn--active" data-range="24h">24h</button>
				<button type="button" class="wpsp-range-btn" data-range="7d">7d</button>
			</div>
		</div>
		<div class="wpsp-chart-container">
			<canvas id="wpsp-page-chart"></canvas>
		</div>
		<div class="wpsp-percentiles" id="wpsp-page-percentiles"></div>
	</div>

	<div class="wpsp-section">
		<h3><?php esc_html_e( 'エラーログ', 'site-pulse' ); ?></h3>
		<?php if ( empty( $page_errors ) ) : ?>
			<p class="wpsp-empty"><?php esc_html_e( '直近のエラーはありません。', 'site-pulse' ); ?></p>
		<?php else : ?>
			<table class="widefat striped wpsp-table">
				<thead>
					<tr>
						<th><?php esc_html_e( '日時', 'site-pulse' ); ?></th>
						<th><?php esc_html_e( 'URL', 'site-pulse' ); ?></th>
						<th><?php esc_html_e( 'ステータス', 'site-pulse' ); ?></th>
						<th><?php esc_html_e( '応答時間', 'site-pulse' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $page_errors as $err ) : ?>
						<tr>
							<td class="wpsp-nowrap"><?php echo esc_html( WPSP_Dashboard::to_local_time( $err['created_at'] ) ); ?></td>
							<td class="wpsp-mono"><?php echo esc_html( $err['target'] ); ?></td>
							<td>
								<?php if ( null === $err['status_code'] ) : ?>
									<span class="wpsp-badge wpsp-badge--page_down"><?php esc_html_e( '接続エラー', 'site-pulse' ); ?></span>
								<?php else : ?>
									<span class="wpsp-badge wpsp-badge--page_slow">HTTP <?php echo esc_html( $err['status_code'] ); ?></span>
								<?php endif; ?>
							</td>
							<td class="wpsp-nowrap wpsp-mono">
								<?php echo null !== $err['response_time_ms'] ? esc_html( $err['response_time_ms'] . 'ms' ) : '—'; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="wpsp-section wpsp-section--compact">
		<h3><?php esc_html_e( '監視 URL', 'site-pulse' ); ?></h3>
		<p class="wpsp-note">
			<?php esc_html_e( 'URL の管理は設定ページから行えます。現在の監視対象:', 'site-pulse' ); ?>
		</p>
		<ul class="wpsp-url-list">
			<?php foreach ( $urls as $url ) : ?>
				<li class="wpsp-url-list__item">
					<span class="dashicons dashicons-admin-site-alt3"></span>
					<?php echo esc_url( $url ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<!-- ===== Level 3: Details — DB ===== -->
	<h2 class="wpsp-group-heading"><?php esc_html_e( 'データベース — 詳細', 'site-pulse' ); ?></h2>

	<div class="wpsp-section">
		<div class="wpsp-section__header">
			<h3><?php esc_html_e( 'CRUD パフォーマンス', 'site-pulse' ); ?></h3>
			<div class="wpsp-range-toggle" data-chart="db">
				<button type="button" class="wpsp-range-btn wpsp-range-btn--active" data-range="24h">24h</button>
				<button type="button" class="wpsp-range-btn" data-range="7d">7d</button>
			</div>
		</div>
		<div class="wpsp-chart-container">
			<canvas id="wpsp-db-chart"></canvas>
		</div>
		<div class="wpsp-percentiles" id="wpsp-db-percentiles"></div>
	</div>

	<div class="wpsp-section">
		<h3><?php esc_html_e( 'DB 飽和度', 'site-pulse' ); ?></h3>
		<div class="wpsp-saturation">
			<div class="wpsp-saturation__item">
				<span class="wpsp-saturation__label">
					<?php esc_html_e( 'Autoload サイズ', 'site-pulse' ); ?>
					<span class="wpsp-info" tabindex="0">
						<span class="wpsp-info__icon">i</span>
						<span class="wpsp-info__tooltip"><?php esc_html_e( 'wp_options の autoload=yes のデータ合計サイズ。全ページ読み込み時に毎回メモリに展開されるため、大きいほどサイト全体が遅くなります。800KB以下が理想、1.5MB超は不要なオプションの autoload 解除やプラグインの見直しが必要です。', 'site-pulse' ); ?></span>
					</span>
				</span>
				<span class="wpsp-saturation__value"><?php echo esc_html( number_format( $al_size ) . ' KB' ); ?></span>
				<div class="wpsp-gauge">
					<div class="wpsp-gauge__bar <?php echo esc_attr( $al_class ); ?>" style="width:<?php echo esc_attr( $al_pct ); ?>%"></div>
				</div>
			</div>

			<div class="wpsp-saturation__item">
				<span class="wpsp-saturation__label">
					<?php esc_html_e( 'Autoload オプション数', 'site-pulse' ); ?>
					<span class="wpsp-info" tabindex="0">
						<span class="wpsp-info__icon">i</span>
						<span class="wpsp-info__tooltip"><?php esc_html_e( 'autoload=yes が設定された wp_options の行数。行儀の悪いプラグインが大量に登録しがちです。500以下が理想、1,000超の場合は各プラグインの autoload 設定を確認してください。', 'site-pulse' ); ?></span>
					</span>
				</span>
				<span class="wpsp-saturation__value"><?php echo esc_html( number_format( $al_cnt ) ); ?></span>
				<div class="wpsp-gauge">
					<div class="wpsp-gauge__bar <?php echo esc_attr( $al_cnt_class ); ?>" style="width:<?php echo esc_attr( $al_cnt_pct ); ?>%"></div>
				</div>
			</div>

			<div class="wpsp-saturation__item">
				<span class="wpsp-saturation__label">
					<?php esc_html_e( '期限切れトランジェント', 'site-pulse' ); ?>
					<span class="wpsp-info" tabindex="0">
						<span class="wpsp-info__icon">i</span>
						<span class="wpsp-info__tooltip"><?php esc_html_e( '有効期限が切れたまま wp_options に残っている一時データの数。通常は WP-Cron が自動削除しますが、cron が正常に動いていないと溜まり続けます。0が理想で、増え続ける場合はサーバーの cron 設定を確認してください。', 'site-pulse' ); ?></span>
					</span>
				</span>
				<span class="wpsp-saturation__value"><?php echo esc_html( number_format( $exp ) ); ?></span>
				<div class="wpsp-gauge">
					<div class="wpsp-gauge__bar <?php echo esc_attr( $exp_class ); ?>" style="width:<?php echo esc_attr( $exp_pct ); ?>%"></div>
				</div>
			</div>

			<div class="wpsp-saturation__item">
				<span class="wpsp-saturation__label">
					<?php esc_html_e( 'リビジョン倍率', 'site-pulse' ); ?>
					<span class="wpsp-info" tabindex="0">
						<span class="wpsp-info__icon">i</span>
						<span class="wpsp-info__tooltip"><?php esc_html_e( '投稿リビジョン数を投稿数で割った値。WordPress は編集のたびにリビジョンを保存するため、wp_posts テーブルが肥大化します。10x以下が理想で、大きい場合は wp-config.php に define(\'WP_POST_REVISIONS\', 5); などで制限できます。', 'site-pulse' ); ?></span>
					</span>
				</span>
				<span class="wpsp-saturation__value">
					<?php
					printf(
						/* translators: %1$s: revision ratio, %2$s: revision count, %3$s: post count */
						esc_html__( '%1$sx（%2$s件 / 投稿%3$s件）', 'site-pulse' ),
						esc_html( $rev_ratio ),
						esc_html( number_format( $rev_count ) ),
						esc_html( number_format( $post_count ) )
					);
					?>
				</span>
				<div class="wpsp-gauge">
					<div class="wpsp-gauge__bar <?php echo esc_attr( $rev_class ); ?>" style="width:<?php echo esc_attr( $rev_pct ); ?>%"></div>
				</div>
			</div>
		</div>
	</div>

	<div class="wpsp-section">
		<h3><?php esc_html_e( 'スロークエリログ', 'site-pulse' ); ?></h3>
		<?php if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) : ?>
			<p class="wpsp-note" style="color:#d63638;">
				SAVEQUERIES: OFF —
				<?php esc_html_e( 'スロークエリ検知を利用するには、wp-config.php に以下を追加してください:', 'site-pulse' ); ?>
				<code style="display:inline-block;margin-top:4px;">define( 'SAVEQUERIES', true );</code>
			</p>
		<?php elseif ( empty( $slow_queries ) ) : ?>
			<p class="wpsp-empty"><?php esc_html_e( 'スロークエリは検出されていません。', 'site-pulse' ); ?></p>
		<?php else : ?>
			<table class="widefat striped wpsp-table">
				<thead>
					<tr>
						<th><?php esc_html_e( '日時', 'site-pulse' ); ?></th>
						<th><?php esc_html_e( 'SQL', 'site-pulse' ); ?></th>
						<th><?php esc_html_e( '実行時間', 'site-pulse' ); ?></th>
						<th><?php esc_html_e( '呼び出し元', 'site-pulse' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $slow_queries as $q ) : ?>
						<tr>
							<td class="wpsp-nowrap"><?php echo esc_html( WPSP_Dashboard::to_local_time( $q['created_at'] ) ); ?></td>
							<td>
								<span class="wpsp-sql-short" title="<?php echo esc_attr( $q['query_sql'] ); ?>">
									<?php echo esc_html( mb_strimwidth( $q['query_sql'], 0, 100, '...' ) ); ?>
								</span>
							</td>
							<td class="wpsp-nowrap wpsp-mono">
								<?php echo esc_html( $q['execution_time_ms'] . 'ms' ); ?>
							</td>
							<td class="wpsp-mono"><?php echo esc_html( $q['caller'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="wpsp-section">
		<h3><?php esc_html_e( 'クエリ統計', 'site-pulse' ); ?></h3>
		<?php if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) : ?>
			<p class="wpsp-note" style="color:#00a32a;">SAVEQUERIES: ON</p>
		<?php else : ?>
			<p class="wpsp-note" style="color:#d63638;">
				SAVEQUERIES: OFF —
				<?php esc_html_e( 'スロークエリ検知とクエリ統計を利用するには、wp-config.php に以下を追加してください:', 'site-pulse' ); ?>
				<code style="display:inline-block;margin-top:4px;">define( 'SAVEQUERIES', true );</code>
			</p>
		<?php endif; ?>
		<div class="wpsp-query-stats">
			<div class="wpsp-query-stats__total">
				<span class="wpsp-query-stats__label"><?php esc_html_e( '総クエリ数', 'site-pulse' ); ?></span>
				<span class="wpsp-query-stats__value"><?php echo esc_html( $query_stats['total'] ); ?></span>
			</div>
			<div class="wpsp-query-stats__chart-wrap">
				<canvas id="wpsp-query-pie"></canvas>
			</div>
			<div class="wpsp-query-stats__breakdown">
				<div class="wpsp-query-stats__row">
					<span class="wpsp-dot wpsp-dot--select"></span> SELECT: <?php echo esc_html( $query_stats['select'] ); ?>
				</div>
				<div class="wpsp-query-stats__row">
					<span class="wpsp-dot wpsp-dot--insert"></span> INSERT: <?php echo esc_html( $query_stats['insert'] ); ?>
				</div>
				<div class="wpsp-query-stats__row">
					<span class="wpsp-dot wpsp-dot--update"></span> UPDATE: <?php echo esc_html( $query_stats['update'] ); ?>
				</div>
				<div class="wpsp-query-stats__row">
					<span class="wpsp-dot wpsp-dot--delete"></span> DELETE: <?php echo esc_html( $query_stats['delete'] ); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== Alert History (shared) ===== -->
	<h2 class="wpsp-group-heading"><?php esc_html_e( 'アラート履歴', 'site-pulse' ); ?></h2>

	<div class="wpsp-section">
		<?php if ( empty( $alerts ) ) : ?>
			<p class="wpsp-empty"><?php esc_html_e( 'アラートはまだ送信されていません。', 'site-pulse' ); ?></p>
		<?php else : ?>
			<table class="widefat striped wpsp-table">
				<thead>
					<tr>
						<th><?php esc_html_e( '日時', 'site-pulse' ); ?></th>
						<th><?php esc_html_e( '種別', 'site-pulse' ); ?></th>
						<th><?php esc_html_e( '対象', 'site-pulse' ); ?></th>
						<th><?php esc_html_e( 'メッセージ', 'site-pulse' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $alerts as $a ) : ?>
						<tr>
							<td class="wpsp-nowrap"><?php echo esc_html( WPSP_Dashboard::to_local_time( $a['created_at'] ) ); ?></td>
							<td>
								<span class="wpsp-badge wpsp-badge--<?php echo esc_attr( $a['alert_type'] ); ?>">
									<?php echo esc_html( $a['alert_type'] ); ?>
								</span>
							</td>
							<td class="wpsp-mono"><?php echo esc_html( $a['target'] ); ?></td>
							<td><?php echo esc_html( $a['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
