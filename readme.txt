=== WP Site Pulse ===
Contributors: otokichi3
Tags: monitoring, uptime, performance, dashboard, slow-query
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight site monitoring — page uptime, response time, DB performance, and slow query detection with a built-in dashboard. No external service required.

== Description ==

WP Site Pulse monitors your WordPress site health from the inside. No external SaaS needed — everything runs within WordPress using WP-Cron.

**Features:**

* **Page Uptime Monitoring** — Periodically checks registered pages for HTTP status and response time.
* **DB Performance Monitoring** — Runs fixed CRUD test queries and records execution time.
* **Slow Query Detection** — Logs queries exceeding 500ms (requires `SAVEQUERIES`).
* **Email Alerts** — Notifies the site admin when a page goes down, responds slowly, or DB performance degrades.
* **Built-in Dashboard** — View all metrics, charts, and alert history from wp-admin.

**Why WP Site Pulse?**

Most monitoring plugins require an external service or subscription. WP Site Pulse works entirely within your WordPress installation — ideal for shared hosting (e.g., XServer) where you can't install server-level monitoring tools.

== Installation ==

1. Upload the `wp-site-pulse` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to "Site Pulse" in the admin menu to view the dashboard.

For reliable cron execution, set up a server-side cron job to hit `wp-cron.php` every 15 minutes:

`*/15 * * * * wget -q -O /dev/null https://yourdomain.com/wp-cron.php?doing_wp_cron`

== Frequently Asked Questions ==

= Does this plugin require an external service? =

No. Everything runs within WordPress. No API keys or subscriptions needed.

= Will this slow down my site? =

The monitoring checks run via WP-Cron in the background and do not affect page load for visitors. Slow query detection requires `SAVEQUERIES` which has a performance impact — enable it only when needed.

= What happens when I uninstall the plugin? =

All custom tables and stored options are removed cleanly.

== Changelog ==

= 0.1.0 =
* Initial development version.
