# Site Pulse — Development Guide

## プロジェクト概要

WordPress サイトの監視プラグイン。ページ死活・応答速度・DB パフォーマンス・スロークエリを監視し、管理画面ダッシュボードとメールアラートを提供する。

## 技術スタック

- PHP 7.4+（WordPress コーディング規約準拠）
- WordPress Plugin API（フック、WP-Cron、`$wpdb`、`wp_remote_get`）
- Chart.js（CDN、管理画面のみ）
- 国際化: `__()` / `_e()` で日英対応、Text Domain: `wp-site-pulse`

## 開発ルール

### コーディング規約
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) に準拠
- クラス名: `WPSP_Page_Checker` のように `WPSP_` プレフィックス
- 定数: `WPSP_` プレフィックス（例: `WPSP_VERSION`）
- フック名: `wpsp_` プレフィックス
- オプション名: `wpsp_` プレフィックス
- テーブル名: `{$wpdb->prefix}site_pulse_*`

### ファイル命名
- クラスファイル: `class-{name}.php`（例: `class-page-checker.php`）
- ビュー: `admin/views/` 配下に PHP テンプレート

### セキュリティ
- 直接アクセス防止: 全 PHP ファイル先頭に `if ( ! defined( 'ABSPATH' ) ) exit;`
- nonce 検証: フォーム送信時は必ず `wp_nonce_field` / `wp_verify_nonce`
- 権限チェック: 管理画面は `manage_options` 権限
- SQL: `$wpdb->prepare()` を必ず使用
- 出力: `esc_html()`, `esc_attr()`, `esc_url()` でエスケープ

### 国際化
- 翻訳可能な文字列は `__( 'text', 'wp-site-pulse' )` で囲む
- Text Domain: `wp-site-pulse`
- Domain Path: `/languages`

## 監視の固定閾値

| 項目 | 閾値 | 定数名 |
|------|------|--------|
| ページ応答時間 | 3秒 | `WPSP_PAGE_SLOW_THRESHOLD_MS` (3000) |
| DB 操作時間 | 1秒 | `WPSP_DB_SLOW_THRESHOLD_MS` (1000) |
| スロークエリ | 500ms | `WPSP_SLOW_QUERY_THRESHOLD_MS` (500) |
| アラート抑制 | 1時間 | `WPSP_ALERT_COOLDOWN_SEC` (3600) |
| データ保持 | 7日 | `WPSP_DATA_RETENTION_DAYS` (7) |
| チェック間隔 | 15分 | WP-Cron interval |

## カスタムテーブル

```
wp_site_pulse_checks       — ページ・DB チェック結果（7日保持）
wp_site_pulse_slow_queries — スロークエリログ（100件保持）
wp_site_pulse_alerts       — アラート履歴（100件保持）
```

## WordPress.org 公開に向けて

- `readme.txt` は WordPress.org フォーマット準拠
- `uninstall.php` でテーブル・オプションを完全削除
- ライセンス: GPL-2.0-or-later
- 外部リソース: Chart.js CDN のみ（管理画面限定）
