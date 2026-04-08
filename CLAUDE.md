# Site Pulse — Development Guide

## プロジェクト概要

WordPress サイトの監視プラグイン。ページ死活・応答速度・DB パフォーマンス・スロークエリを監視し、管理画面ダッシュボードとメールアラートを提供する。

WordPress.org プラグインディレクトリに申請済み（スラッグ: `site-pulse`）。

## 技術スタック

- PHP 7.4+（WordPress コーディング規約準拠）
- WordPress Plugin API（フック、WP-Cron、`$wpdb`、`wp_remote_get`）
- Chart.js（CDN、管理画面のみ）
- 国際化: `__()` / `_e()` で日英対応、Text Domain: `site-pulse`

## 開発ルール

### コーディング規約
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) に準拠
- クラス名: `WPSP_Page_Checker` のように `WPSP_` プレフィックス
- 定数: `WPSP_` プレフィックス（例: `WPSP_VERSION`）
- フック名: `wpsp_` プレフィックス
- オプション名: `wpsp_` プレフィックス
- テーブル名: `{$wpdb->prefix}site_pulse_*`

### ファイル命名
- メインファイル: `site-pulse.php`
- クラスファイル: `class-{name}.php`（例: `class-page-checker.php`）
- ビュー: `admin/views/` 配下に PHP テンプレート

### セキュリティ
- 直接アクセス防止: 全 PHP ファイル先頭に `if ( ! defined( 'ABSPATH' ) ) exit;`
- nonce 検証: フォーム送信時は必ず `wp_nonce_field` / `wp_verify_nonce`
- 権限チェック: 管理画面は `manage_options` 権限
- SQL: `$wpdb->prepare()` を必ず使用
- 出力: `esc_html()`, `esc_attr()`, `esc_url()` でエスケープ
- `/* translators: */` コメント: `__()` にプレースホルダがある場合は直前に必須

### 国際化
- 翻訳可能な文字列は `__( 'text', 'site-pulse' )` で囲む
- Text Domain: `site-pulse`
- Domain Path: `/languages`
- デフォルト言語: 日本語（英語は翻訳ファイルで提供）

## 監視の固定閾値

| 項目 | 閾値 | 定数名 |
|------|------|--------|
| ページ応答時間 | 3秒 | `WPSP_PAGE_SLOW_THRESHOLD_MS` (3000) |
| DB 操作時間 | 1秒 | `WPSP_DB_SLOW_THRESHOLD_MS` (1000) |
| スロークエリ | 設定画面で変更可（デフォルト500ms） | `WPSP_SLOW_QUERY_THRESHOLD_MS` (500) |
| アラート抑制 | 1時間 | `WPSP_ALERT_COOLDOWN_SEC` (3600) |
| データ保持 | 7日 | `WPSP_DATA_RETENTION_DAYS` (7) |
| チェック間隔 | 15分 | WP-Cron interval |

## カスタムテーブル

```
wp_site_pulse_checks       — ページ・DB チェック結果（7日保持）
wp_site_pulse_slow_queries — スロークエリログ（100件保持）
wp_site_pulse_alerts       — アラート履歴（100件保持）
```

## 日時の扱い

- DB には UTC で保存（`current_time( 'mysql', true )`）
- ダッシュボードのテーブル表示は `WPSP_Dashboard::to_local_time()` で WP のタイムゾーン設定に変換
- Chart.js のグラフは JS 側で UTC → ブラウザのローカルタイムに変換（`fixUtcTimestamps()`）

## 認証付き監視

- URL ごとに認証の ON/OFF を設定可能（`wpsp_auth_urls` オプション）
- `wp_generate_auth_cookie()` で有効期限 60 秒の一時 Cookie を生成
- 管理者または編集者ロールのユーザーのみ指定可

## 開発コマンド（Makefile）

| コマンド | 内容 |
|---------|------|
| `make deploy` | ローカル Docker WP 環境にコピー |
| `make deploy-xserver` | XServer にデプロイ |
| `make zip` | `site-pulse.zip` を作成 |

## WordPress.org 公開に向けて

- `readme.txt` は WordPress.org フォーマット準拠
- `uninstall.php` でテーブル・オプションを完全削除
- ライセンス: GPL-2.0-or-later
- 外部リソース: Chart.js CDN のみ（管理画面限定、readme.txt に開示済み）
- Plugin Check (PCP): 0 errors
- プラグイン名に "WP" プレフィックスは使用不可（WordPress.org の制約）
