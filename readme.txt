=== Hypercart Server Monitor MKII ===
Contributors: hypercart
Tags: monitoring, performance, server, health, metrics
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitors server performance with synthetic PHP benchmarks every 15 minutes with email alerts and admin dashboard.

== Description ==

Hypercart Server Monitor MKII is a lightweight plugin that continuously monitors your server's performance using a synthetic PHP benchmark that works on all environments (Local, staging, production, any OS).

**Benchmark Tests:**

* **Mathematical Operations** - 50,000 iterations of sqrt/sin/cos calculations
* **String Operations** - 10,000 MD5 hashes with substring management
* **Array Operations** - 10,000 elements with sort/filter/map/reduce

**Features:**

* Runs automatically every 15 minutes via WP-Cron
* Calculates a combined health score (0-100)
* Stores last 24 hours of metrics in JSON format
* Email notifications with score in subject line
* Circuit breaker protection to prevent runaway processes
* FSM-light state management for reliability
* Integrates with Hypercart Helper for time management and logging

**Requirements:**

* Hypercart Helper v1.1.2+ (required dependency)
* PHP 7.4 or higher
* WordPress 5.8 or higher

**Current Version (0.4.0):**

Phase 5 complete - Email notifications with dynamic subject line:
* ✅ Dependency check for Hypercart Helper
* ✅ FSM state store with transitions
* ✅ WP-Cron scheduling (every 15 minutes)
* ✅ **Synthetic Benchmark** - PHP compute tests (math, string, array ops)
* ✅ Scoring service (0-100 scale based on execution time)
* ✅ JSON repository with atomic writes and 24h pruning
* ✅ Circuit breaker and lock protection
* ✅ Centralized logging via Hypercart_Logger
* ✅ **Admin Dashboard** - Performance score, benchmark details, 24h chart
* ✅ **Manual Test Runner** - Test without saving to database
* ✅ **Email Notifications** - Automatic emails with score in subject line
* ✅ **Manual Test Email** - Send test email with most recent data
* ✅ **Log Viewer** - Browse and filter logs by level
* ✅ **Debug Panel** - FSM state, cron status, file status, lock status

**Coming Soon:**

* Email reporting with score in subject (Phase 5)
* Self-test diagnostics (Phase 6)

== Installation ==

1. Install and activate **Hypercart Helper** plugin (required dependency)
2. Upload the plugin files to `/wp-content/plugins/Hypercart-Server-Monitor-MKII/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. The plugin will automatically start monitoring every 15 minutes

== Frequently Asked Questions ==

= Does this plugin require Hypercart Helper? =

Yes, Hypercart Helper v1.1.2+ is required for time management, logging, admin tabs, and charts functionality.

= How often does it check server health? =

Every 15 minutes via WP-Cron.

= Where is the data stored? =

Metrics are stored in a JSON file at `wp-content/uploads/hypercart-server-monitor/health-data.json`. Logs are stored in `wp-content/hypercart-logs/` via Hypercart_Logger.

= How long is data retained? =

The JSON file keeps the last 24 hours of metrics (auto-pruned). Log files persist indefinitely for manual archiving.

= Does the benchmark work on all systems? =

Yes! The synthetic PHP benchmark works on all operating systems and environments (Local, staging, production, Windows, macOS, Linux) because it only uses standard PHP functions.

== Changelog ==

= 0.4.0 - 2026-01-10 =
* Added: Email notifications sent automatically after each benchmark run
* Added: Dynamic subject line with score and benchmark time
* Added: HTML email body with full benchmark details
* Added: "Email" tab in admin UI
* Added: "Send Test Email" button (uses most recent saved data)
* Added: FSM state "emailing" in workflow (writing → emailing → completed)
* Changed: Email failures logged but don't stop monitoring workflow
* Benefit: Instant notification of performance issues
* Benefit: Score visible in inbox without opening email
* Benefit: Test email functionality for debugging SMTP

= 0.3.0 - 2026-01-10 =
* **BREAKING CHANGE**: Replaced system metrics (CPU, Memory, Disk) with single synthetic PHP benchmark
* Added: BenchmarkCollector - runs 3 iterations of compute-intensive PHP tasks
* Changed: Scoring now based on execution time (lower = better)
* Changed: Dashboard shows benchmark details (avg/min/max/iterations)
* Changed: Manual Test shows benchmark execution times
* Removed: CpuLoadCollector, MemoryCollector, DiskCollector (deprecated)
* Benefit: Works on ALL environments (Local, staging, production, any OS)
* Benefit: Simpler codebase (1 collector vs 3)
* Benefit: Focus on framework reliability (scheduling, FSM, email)

= 0.2.0 - 2026-01-10 =
* Added: Admin UI with tabbed navigation (Dashboard, Manual Test, Logs, Debug)
* Added: Dashboard tab with health score display, metrics table, and 24h chart
* Added: Manual Test Runner for testing without saving to database
* Added: Log Viewer with file selector and level filtering
* Added: Debug Panel showing FSM state, cron status, file status, lock status
* Changed: Minimum Hypercart Helper version to 1.1.2+ (for Admin Tabs and Charts)
* Changed: Settings link renamed to "Dashboard"
* Changed: Admin menu moved to top-level with custom icon

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

