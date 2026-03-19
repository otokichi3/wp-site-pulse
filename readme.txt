=== Site Pulse ===
Contributors: otokichi3
Tags: monitoring, uptime, performance, dashboard, slow-query
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Internal site monitoring for WordPress. Checks page uptime, DB performance, and slow queries.

== Description ==

Site Pulse is a monitoring plugin that runs entirely inside WordPress. It checks your pages and database on a schedule using WP-Cron and shows the results on a dashboard in wp-admin. When something goes wrong, it sends you an email.

No external monitoring service or API key required.

**What it monitors:**

* Page HTTP status and response time (including login-required pages)
* DB read/write performance via test queries (SELECT, INSERT, UPDATE, DELETE)
* Slow queries (requires `SAVEQUERIES` to be enabled)
* DB health indicators: autoload option size, expired transients, post revision bloat

**Alerts:**

The plugin sends email alerts when a page returns an error, responds too slowly, or a DB test query fails. You can choose which alert types to enable, and duplicate alerts are suppressed for 1 hour.

**Dashboard:**

The admin dashboard shows an overall site health indicator, then breaks down into page monitoring and DB status. Each section has charts (powered by Chart.js), error logs, and detail views.

= External Services =

This plugin loads Chart.js from jsDelivr CDN on the admin dashboard page only. No site data is sent externally.

* `https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js`
* `https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js`
* [jsDelivr Terms of Use](https://www.jsdelivr.com/terms) / [Privacy Policy](https://www.jsdelivr.com/privacy-policy-jsdelivr-net)

== Installation ==

1. Upload the `site-pulse` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open "Site Pulse" in the admin menu.
4. Go to "Site Pulse > Settings" to add URLs and configure alerts.

WP-Cron depends on site traffic to run. For reliable monitoring, add a server cron:

`*/15 * * * * wget -q -O /dev/null https://yourdomain.com/wp-cron.php?doing_wp_cron`

== Frequently Asked Questions ==

= Does this need an external service? =

No. Everything runs inside WordPress.

= Will it slow down my site? =

Checks run in the background via WP-Cron. Visitors are not affected. Slow query detection uses `SAVEQUERIES` which does add overhead — only enable it when you need it.

= Can it monitor pages that require login? =

Yes. In Settings, enable authenticated monitoring and pick a user account. The plugin creates temporary auth cookies (valid for 60 seconds) for each check.

= What happens on uninstall? =

All tables and options created by the plugin are deleted.

= How long is data kept? =

Check results are kept for 7 days. Slow query logs and alert history are capped at 100 entries each.

== Screenshots ==

1. Dashboard overview with health indicators.
2. Page response time chart.
3. DB saturation gauges with info tooltips.
4. Settings page.

== Changelog ==

= 0.1.0 (2026-03-19) =
Initial release.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
