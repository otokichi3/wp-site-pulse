# WP Site Pulse

WordPress サイトの死活監視・応答速度・DB パフォーマンス・スロークエリを監視する軽量プラグイン。
外部サービス不要 — WordPress + WP-Cron だけで完結。

## 機能

| 機能 | 概要 |
|------|------|
| ページ死活監視 | 登録 URL の HTTP ステータスと応答時間を定期チェック |
| DB パフォーマンス監視 | SELECT / INSERT / UPDATE / DELETE のテストクエリ実行時間を記録 |
| スロークエリ検知 | 500ms 超のクエリをログ（`SAVEQUERIES` 有効時のみ） |
| メールアラート | 異常検知時にサイト管理者へ通知（同一アラート1時間に1回まで） |
| ダッシュボード | 管理画面で全メトリクス・グラフ・アラート履歴を確認 |

## 必要環境

- WordPress 6.0+
- PHP 7.4+

## ディレクトリ構成

```
wp-site-pulse/
├── wp-site-pulse.php          # メイン（プラグインヘッダー、有効化/無効化フック）
├── uninstall.php              # テーブル・オプション削除
├── readme.txt                 # WordPress.org 掲載用
├── includes/
│   ├── class-installer.php    # テーブル作成
│   ├── class-page-checker.php # ページ死活チェック
│   ├── class-db-checker.php   # DB パフォーマンスチェック
│   ├── class-slow-query.php   # スロークエリ検知
│   ├── class-alerter.php      # メールアラート・抑制
│   └── class-cleanup.php      # データ保持期間管理
├── admin/
│   ├── class-dashboard.php    # 管理画面ページ登録・レンダリング
│   ├── views/
│   │   └── dashboard.php      # ダッシュボード HTML テンプレート
│   ├── css/
│   │   └── dashboard.css
│   └── js/
│       └── dashboard.js       # Chart.js グラフ描画
└── languages/                 # 国際化（日英対応）
```

## カスタムテーブル

プラグイン有効化時に以下のテーブルが作成されます:

| テーブル | 用途 | 保持期間 |
|---------|------|---------|
| `wp_site_pulse_checks` | ページ・DB チェック結果 | 7日間 |
| `wp_site_pulse_slow_queries` | スロークエリログ | 直近100件 |
| `wp_site_pulse_alerts` | アラート送信履歴 | 直近100件 |

## 監視の閾値

| 項目 | 閾値 |
|------|------|
| ページ応答時間 | 3秒 |
| DB 操作時間 | 1秒 |
| スロークエリ | 500ms |

## WP-Cron について

WP-Cron はサイトへのアクセスをトリガーに動作します。
確実な定期実行のため、サーバー側 cron の設定を推奨:

```bash
*/15 * * * * wget -q -O /dev/null https://yourdomain.com/wp-cron.php?doing_wp_cron
```

## ライセンス

GPL-2.0-or-later
