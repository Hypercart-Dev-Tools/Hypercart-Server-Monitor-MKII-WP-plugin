=== WP Server Performance Monitor ===
Contributors: yourusername
Tags: monitoring, performance, server, health, metrics
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitors server health (CPU, Memory, Disk) every 15 minutes with email alerts and admin dashboard.

== Description ==

WP Server Performance Monitor is a lightweight plugin that continuously monitors your server's health by tracking three key metrics:

* **CPU Load** - 1-minute load average, normalized by CPU cores
* **Memory Usage** - Percentage of RAM used
* **Disk Space** - Percentage of disk space free

**Features:**

* Runs automatically every 15 minutes via WP-Cron
* Calculates a combined health score (0-100)
* Stores last 24 hours of metrics in JSON format
* Email notifications with score in subject line
* Circuit breaker protection to prevent runaway processes
* FSM-light state management for reliability
* Integrates with Hypercart Helper for time management and logging

**Requirements:**

* Hypercart Helper v1.0.0+ (required dependency)
* PHP 7.4 or higher
* WordPress 5.8 or higher

**Current Version (0.1.0):**

This is the first iteration with core functionality:
* ✅ Dependency check for Hypercart Helper
* ✅ FSM state store with transitions
* ✅ WP-Cron scheduling (every 15 minutes)
* ✅ Metric collectors (CPU, Memory, Disk) with fallbacks
* ✅ Scoring service (0-100 scale)
* ✅ JSON repository with atomic writes and 24h pruning
* ✅ Circuit breaker and lock protection
* ✅ Centralized logging via Hypercart_Logger

**Coming Soon:**

* Admin UI with Manual Test Runner
* Today's Log Viewer
* Debug Panel with file inspection
* Email reporting with score in subject
* Self-test diagnostics

== Installation ==

1. Install and activate **Hypercart Helper** plugin (required dependency)
2. Upload the plugin files to `/wp-content/plugins/wp-server-performance-monitor/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. The plugin will automatically start monitoring every 15 minutes

== Frequently Asked Questions ==

= Does this plugin require Hypercart Helper? =

Yes, Hypercart Helper v1.0.0+ is required for time management and logging functionality.

= How often does it check server health? =

Every 15 minutes via WP-Cron.

= Where is the data stored? =

Metrics are stored in a JSON file at `wp-content/uploads/wp-server-monitor/health-data.json`. Logs are stored in `wp-content/hypercart-logs/` via Hypercart_Logger.

= How long is data retained? =

The JSON file keeps the last 24 hours of metrics (auto-pruned). Log files persist indefinitely for manual archiving.

= What happens if a metric can't be collected? =

The plugin has fallback handling. If a metric is unavailable (e.g., on Windows), it's marked as 'unknown' and excluded from scoring.

== Changelog ==

= 0.1.0 - 2026-01-10 =
* Initial release
* Core monitoring functionality (CPU, Memory, Disk)
* FSM state management
* JSON persistence with atomic writes
* Circuit breaker protection
* Hypercart Helper integration
* WP-Cron scheduling (every 15 minutes)

== Upgrade Notice ==

= 0.1.0 =
Initial release. Requires Hypercart Helper v1.0.0+.

