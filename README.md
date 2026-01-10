````md
# WP Server Performance Monitor — Project Plan

> **IMPORTANT FOR LLM AGENTS:** This plugin integrates with **Hypercart Helper** for time management and logging.
> **REQUIRED READING:** See `HH-INTEGRATION-GUIDE.md` for complete API documentation before implementing any time/logging features.

## Table of Contents
1. Overview and Goals
2. Scope
3. Chosen Metrics and Scoring Model
4. Data Model and JSON Single Source of Truth
5. Architecture and Separation of Concerns
6. FSM-Light Pattern
7. Scheduling (Every 15 Minutes)
8. Admin Settings + Debug Panel UI
9. Email Reporting
10. Circuit Breaker and Runaway Protection
11. Self-Test UI + PHPUnit (UI + Terminal)
12. Additional Debugging Features (Visibility Without Over-Engineering)
13. Plugin UX (Settings Link on Plugins Page)
14. Implementation Phases and Milestones
15. Security, Reliability, and Performance Considerations
16. Acceptance Criteria
17. File/Folder Layout
18. Testing Strategy
19. Future Extensibility
20. Hypercart Helper Integration

---

## Actionable Checklist (start here)
- [ ] **FIRST:** Verify Hypercart Helper is installed and active (see section 20)
- [ ] JSON data file will live here: `wp-content/uploads/wp-server-monitor/health-data.json`
- [ ] Logs will be written to: `wp-content/hypercart-logs/hypercart-YYYY-MM-DD.log` (via Hypercart_Logger)
- [ ] Implement dependency check for Hypercart Helper (see `HH-INTEGRATION-GUIDE.md`)
- [ ] Implement metric collectors (Load, Memory, Disk) with safe fallbacks
- [ ] Implement scoring (0–100), weights, and thresholds
- [ ] Implement JSON repository with atomic writes
- [ ] Implement FSM-light state store + transitions + last error
- [ ] Implement scheduler (WP-Cron) + admin-visible CRON status
- [ ] Implement email reporter (wp_mail) every run **with composite score in subject line**
- [ ] Add circuit breaker: lock, rate limit, failure counter, file size cap, and backoff
- [ ] Build Settings page with sections:
  - [ ] **Manual Test Runner** (run metrics without logging, see live results)
  - [ ] **24-Hour Metrics Table** (from JSON, newest first, local time)
  - [ ] **Today's Log Viewer** (parse and display today's log entries in table)
  - [ ] **Debug Panel** (FSM state, cron status, file info with "View File" button)
  - [ ] **Self Test** (comprehensive system diagnostics)
  - [ ] **Settings** (email recipient, enable/disable features)
- [ ] Add “Self Test” section/page: run checks from admin UI (nonce protected)
- [ ] Add PHPUnit tests runnable in terminal + minimal mocks/stubs
- [ ] Add plugin action link “Settings” on Plugins listing
- [ ] Add PHPDoc across public APIs and major internals
- [ ] Integrate `Hypercart_Time` for all time operations (NO direct `time()` calls)
- [ ] Integrate `Hypercart_Logger` for all logging (NO separate log files)
- [ ] **NEW:** Add `Hypercart_Time::get_today_utc_date()` to Hypercart Helper (for log file lookup)
- [ ] **NEW:** Implement log file parser (read, filter by plugin, display in table)
- [ ] **NEW:** Add AJAX endpoint for Manual Test Runner
- [ ] **NEW:** Add "View File" modal for raw JSON inspection
- [ ] On-going audit: DRY helpers, single write paths, single SOT, logging/errors

---

## 1. Overview and Goals
Build a WordPress plugin that monitors server health for a busy e-commerce site by:
- Running every 15 minutes
- Computing **three server metrics** and a **single balanced combined score**
- Writing results to a **UTC-timestamped JSON file** (single source of truth)
- Displaying last **24 hours** in WP Admin (reverse chronological)
- Emailing the combined score + raw metrics each run
- Using an **FSM-light** approach to support future features
- Providing a **Self Test UI** plus **PHPUnit** tests runnable via terminal

---

## 2. Scope

### In Scope
- Metric computation and normalization
- JSON persistence (24h rolling window in single data file)
- Logging via Hypercart_Logger (daily log files, no auto-deletion)
- WP Admin settings page:
  - Table view (last 24h of metrics)
  - Debug panel (FSM state, CRON status, etc.)
- Email notification every 15 minutes with score in subject
- Circuit breaker / runaway protection
- Self Test UI + PHPUnit tests

### Out of Scope (for now)
- External dashboards, charts, or remote storage
- Multi-server aggregation
- Alert thresholds and paging escalation (future)

---

## 3. Chosen Metrics and Scoring Model

### Metrics (computed every 15 minutes)
1. **CPU Load (1-minute load average, normalized by CPU cores)**
   - Source: `sys_getloadavg()` (fallback: “unknown”)
   - Normalize: `load1 / cores` (cores via `wp_get_cpu_count()` equivalent; fallback 1)
2. **Memory Usage (% used)**
   - Source (Linux): `/proc/meminfo` (MemTotal, MemAvailable)
   - Fallback: if unavailable, mark as “unsupported” and reduce weight
3. **Disk Free (% free)**
   - Source: `disk_free_space()` and `disk_total_space()` on `ABSPATH` or `WP_CONTENT_DIR`

### Combined Score (0–100, higher is better)
- Convert each metric to a **subscore (0–100)** then compute a weighted average.

Example weights (tunable):
- CPU Load subscore: **40%**
- Memory subscore: **35%**
- Disk free subscore: **25%**

#### Subscore examples (simple, explainable)
- CPU normalized load:
  - `<= 0.7` → 100
  - `0.7–1.0` → linear down to 70
  - `1.0–2.0` → linear down to 20
  - `> 2.0` → 0
- Memory used percent:
  - `<= 70%` → 100
  - `70–85%` → linear down to 60
  - `85–95%` → linear down to 20
  - `> 95%` → 0
- Disk free percent:
  - `>= 20%` → 100
  - `10–20%` → linear down to 50
  - `5–10%` → linear down to 20
  - `< 5%` → 0

> Keep the math deterministic and documented so future alerting can build on it.

---

## 4. Data Model and JSON Single Source of Truth

### Important: Two Separate File Systems

This plugin uses **two distinct file storage systems**:

1. **JSON Data File** (metrics storage)
   - Location: `wp-content/uploads/wp-server-monitor/health-data.json`
   - Purpose: Store last 24 hours of server metrics (single source of truth for metrics)
   - Retention: Auto-pruned to keep only last 24h (~96 samples)
   - Format: Structured JSON with timestamps and metric values

2. **Log Files** (operational logging)
   - Location: `wp-content/hypercart-logs/hypercart-YYYY-MM-DD.log`
   - Purpose: Operational logs (info, warnings, errors) via `Hypercart_Logger`
   - Retention: **No automatic deletion** — files persist for manual review/archiving
   - Format: Plain text log entries with timestamps
   - Daily rotation: New file created each day (UTC)

**Do not confuse these two systems.** The JSON file stores metric data; the log files store operational events.

### JSON data file characteristics
- **Location:** `wp-content/uploads/wp-server-monitor/health-data.json`
- **UTC timestamps** (ISO8601, e.g. `2026-01-10T23:45:00Z`)
- Stores last **24 hours** only (rolling window, ~96 samples at 15-min intervals)
- Reverse chronological display in UI (but stored order can be chronological for easier pruning)
- **Separate from logs:** This is metric data only; operational logs go to Hypercart_Logger

### JSON schema (proposed)
```json
{
  "version": 1,
  "updated_utc": "2026-01-10T23:45:00Z",
  "samples": [
    {
      "ts_utc": "2026-01-10T23:45:00Z",
      "score": 82,
      "raw": {
        "cpu_load_1m_norm": 0.63,
        "mem_used_pct": 71.2,
        "disk_free_pct": 28.4
      },
      "meta": {
        "collector": "wp-server-monitor",
        "duration_ms": 53
      }
    }
  ]
}
````

### Single write path + atomicity

* Read JSON data file once, prune old samples (>24h), append new sample, write once per run.
* Write atomically:

  1. Write to `health-data.json.tmp`
  2. `rename()` to `health-data.json` (atomic on same filesystem)
* Apply file permissions and directory creation once via installer.
* **Note:** Pruning only applies to the JSON data file (24h window). Log files persist indefinitely.

---

## 5. Architecture and Separation of Concerns

### Key components

* **MetricsCollectorInterface** + 3 collectors
* **ScoringService** (raw → subscores → combined)
* **HealthRepository** (JSON read/prune/write)
* **SchedulerService** (register interval + ensure event scheduled)
* **EmailReporter** (format payload + send)
* **StateMachine (FSM-light)** (state transitions, failure counter, last error)
* **AdminController** (settings page rendering + self-test endpoint)
* **Helpers** (time conversion, formatting, file paths, locking)

### General principles mapping

* Single write paths → HealthRepository only
* Single SOT → JSON file only (WP options store only config/state)
* Separation of concerns → each service has one job
* DRY → shared Helpers for time, file IO, locking, formatting
* Centralized Helpers → `Helpers\*` namespace folder

### Hypercart Helper Integration

**CRITICAL:** This plugin depends on **Hypercart Helper** for:

1. **Time Management** (`Hypercart_Time`)
   * Use `Hypercart_Time::now()` for all timestamps (never use `time()` or `current_time()`)
   * Use `Hypercart_Time::format()` for display in admin UI
   * Use `Hypercart_Time::utc_format()` for JSON storage and logs
   * Use `Hypercart_Time::iso8601()` for email timestamps

2. **Logging** (`Hypercart_Logger`)
   * Use `Hypercart_Logger::info()`, `::warning()`, `::error()` for all logging
   * Plugin slug: `'wp-server-monitor'`
   * Logs stored in: `WP_CONTENT_DIR/hypercart-logs/hypercart-YYYY-MM-DD.log`
   * **DO NOT create separate log files** — use the centralized Hypercart logging system
   * Log format: `[YYYY-MM-DD HH:MM:SS UTC] wp-server-monitor LEVEL: Message {"context":"data"}`

**See `HH-INTEGRATION-GUIDE.md` for complete API documentation.**

---

## 6. FSM-Light Pattern

### Why FSM-light

You want predictable behavior as features grow (alerts, thresholds, retries, remote pushes). A lightweight state machine keeps run logic disciplined without heavy framework overhead.

### States (initial set)

* `idle`
* `scheduled` (event triggered)
* `running`
* `writing`
* `emailing`
* `completed`
* `error`
* `tripped` (circuit breaker engaged)

### Transition rules (example)

* `idle → scheduled → running → writing → emailing → completed → idle`
* Any step can transition to `error`
* Repeated failures can transition to `tripped`

### State storage

* Store current state and metadata in a single WP option:

  * `wp_server_monitor_state` (array: state, updated_utc, failure_count, last_error, last_run_utc, last_duration_ms, lock_status)

---

## 7. Scheduling (Every 15 Minutes)

### WP-Cron event

* Add custom schedule interval `every_15_minutes`
* Register cron hook `wp_server_monitor_run`

### Reliability note

WP-Cron depends on site traffic. For e-commerce, traffic is usually sufficient, but you should still:

* Show admin debug info about last run + next run.
* Recommend optional **real cron** pinging `wp-cron.php` for consistency (documented, not required).

---

## 8. Admin Settings + Debug Panel UI

### Settings page layout

* Title: “Server Performance Monitor”
* Sections:

  1. **Manual Test Runner** (run metrics without logging, see live results)
  2. **24-Hour Metrics Table** (last 24h samples from JSON, newest first)
  3. **Today's Log Viewer** (current day's log entries in table format)
  4. **Debug Panel** (FSM state, cron status, file info)
  5. **Self Test** (system checks + diagnostics)
  6. **Settings** (email recipient, enable/disable emailing, etc.)

### 1. Manual Test Runner (NEW)

**Purpose:** Run metrics collection and scoring WITHOUT persisting to JSON or sending email.

**UI Elements:**
* Button: "Run Manual Test" (nonce-protected)
* Display area showing:
  * Timestamp (local time via `Hypercart_Time::format()`)
  * Combined score with label (Excellent/Good/Warning/Critical)
  * Raw metrics breakdown (CPU, Memory, Disk)
  * Collection duration (ms)
  * Any warnings or fallbacks
  * Color-coded score indicator (green/yellow/orange/red)

**Implementation:**
* AJAX endpoint: `wp_ajax_wp_server_monitor_manual_test`
* Calls collectors + scorer but skips repository write and email
* Returns JSON response for display
* Logs to `Hypercart_Logger::debug()` only (if WP_DEBUG enabled)

**Benefits:**
* See current metrics instantly without waiting for cron
* Test scoring logic without polluting data
* Verify collectors are working
* No side effects (no JSON write, no email)

### 2. 24-Hour Metrics Table

**Purpose:** Display last 24 hours of saved metrics from JSON data file.

**Table columns (left → right):**

1. **Local Time** (using `Hypercart_Time::format()`)
2. **Combined Score** (0–100) with color coding
3. **Raw Metrics** (compact string)
   * e.g. `CPU: 0.63 | MEM: 71.2% | DISK: 28.4%`
4. **Duration** (collection time in ms)

**Display:**
* Reverse chronological (newest first)
* Color-coded scores: Green (90+), Yellow (75-89), Orange (60-74), Red (<60)
* Pagination if >100 samples
* Shows sample count and time range

### 3. Today's Log Viewer (NEW)

**Purpose:** Display current day's log entries in readable table format (no SSH needed).

**Important:** "Today" is based on **WordPress configured timezone** (not UTC).

**Table columns:**

1. **Time** (local time, HH:MM:SS format)
2. **Level** (INFO/WARNING/ERROR/DEBUG) with color coding
3. **Message** (truncated with "show more" for long messages)
4. **Context** (JSON data, collapsible)

**Features:**
* Read from: `WP_CONTENT_DIR/hypercart-logs/hypercart-{TODAY}.log`
  * "TODAY" = current date in WP timezone converted to UTC for filename
  * Use `Hypercart_Time::get_today_utc_date()` to get correct log filename
* Filter by level (show all, errors only, warnings+errors, etc.)
* Auto-refresh option (every 30 seconds)
* "Download Today's Log" button (sends file as download)
* Shows entry count and file size
* Reverse chronological (newest first)
* Parse log format: `[YYYY-MM-DD HH:MM:SS UTC] wp-server-monitor LEVEL: Message {"context":"data"}`
* Convert UTC timestamps to local time for display

**Implementation Notes:**
* Only show entries for `wp-server-monitor` plugin (filter out other plugins)
* Handle large files gracefully (read last N lines, e.g. 500)
* Cache parsed entries for 30 seconds to avoid repeated file reads
* If log file doesn't exist yet today, show "No entries yet today"

**Hypercart Helper Enhancement Needed:**
* Add `Hypercart_Time::get_today_utc_date()` method:
  ```php
  // Returns UTC date string for "today" in WP timezone
  // Example: If WP timezone is PST and it's 2026-01-10 11pm PST,
  // this returns "2026-01-10" (still same day in UTC)
  // But if it's 2026-01-10 1am PST, returns "2026-01-09" (previous day in UTC)
  public static function get_today_utc_date() {
      $wp_now = current_time('timestamp'); // WP timezone
      $utc_timestamp = get_gmt_from_date(date('Y-m-d H:i:s', $wp_now), 'U');
      return gmdate('Y-m-d', $utc_timestamp);
  }
  ```

### Reverse chronological display

* Repository returns samples sorted by `ts_utc desc` (or UI sorts after load)
* Log viewer shows newest entries first

### 4. Debug Panel

Show at minimum:

* Current FSM state + last transition time (local time)
* Last run time (local) + duration (ms)
* Next scheduled run time (local)
* Whether cron event exists
* Lock status (locked/unlocked, lock age)
* Circuit breaker status (failure count, tripped until)
* JSON data file status:
  * Path (with "View File" button to show raw JSON in modal)
  * Writable (yes/no)
  * Size (KB)
  * Sample count
  * Oldest sample timestamp (local)
  * Newest sample timestamp (local)
* Log file status:
  * Today's log file path
  * Size (KB)
  * Entry count (for wp-server-monitor)
  * "View Logs" link (jumps to Log Viewer section)
* Last error message + timestamp (if any)
* WordPress timezone setting (for reference)

**"View File" Button (NEW):**
* Opens modal/expandable section showing raw JSON content
* Syntax-highlighted and formatted
* Read-only
* "Copy to Clipboard" button
* Allows inspection without SSH/FTP

> Later: add WP config flag to hide debug panel; for now always on.

### 5. Self Test Section

(See Section 11 for details)

---

## 9. Email Reporting

### Email Requirements (Phase 5)

**Dynamic Subject Line** - Score must be visible at a glance:

* Format: `[Server Monitor] Score: 100 (Excellent) | Benchmark: 36.5ms`
* Score ranges:
  * 90-100: "Excellent"
  * 75-89: "Good"
  * 60-74: "Warning"
  * Below 60: "Critical"

**Manual Test Email Button:**
* Located in Admin UI (Dashboard or new Email tab)
* Button: "Send Test Email"
* Sends email with **most recently saved** benchmark score and metrics from JSON file
* Does NOT run new benchmark (uses existing data)
* Useful for testing email configuration without waiting for cron

Example subjects:
* `[Server Monitor] Score: 100 (Excellent) | Benchmark: 36.5ms`
* `[Server Monitor] Score: 68 (Warning) | Benchmark: 285.3ms`
* `[Server Monitor] Score: 45 (Critical) | Benchmark: 512.7ms`

Body includes:

* Site name and URL
* UTC timestamp and local timestamp (using `Hypercart_Time::format()`)
* Combined score with label
* Benchmark metrics (avg/min/max/iterations)
* Link to admin dashboard
* Footer with plugin version

Implementation notes:

* Use `wp_mail()` with HTML email format
* Recipient configurable (default: site admin email from `get_option('admin_email')`)
* Ensure email sending happens after JSON write (so the JSON remains SOT)
* Subject line must include score and benchmark time for inbox scanning
* FSM state: `writing` → `emailing` → `completed`

---

## 10. Circuit Breaker and Runaway Protection

### Question: Do we need a circuit breaker to prevent the JSON file process from running away?

Yes—implement a **simple circuit breaker + lock**. Even though this job is small, runaway scenarios happen:

* Cron overlaps due to slow IO or repeated triggers
* JSON corruption triggers repeated failures and retries
* Disk full causes write failures and repeated attempts
* Unexpected loops via mis-registered cron intervals

### Protections (recommended minimum)

1. **Mutex lock** (transient or file lock)

   * Acquire at start; release at end
   * Lock TTL (e.g. 10 minutes) to avoid permanent deadlock
2. **Failure counter + backoff**

   * On failure: increment `failure_count`
   * Trip after N consecutive failures (e.g. 5)
   * While tripped: skip run for a cooldown window (e.g. 60 minutes)
3. **JSON size cap**

   * If file exceeds e.g. 1–2 MB, prune harder or rewrite only last 24h from parsed samples
4. **Atomic writes**

   * Prevent partial JSON that breaks future reads
5. **Corruption handling**

   * If JSON invalid: archive to `health-data.json.bad.<timestamp>` and start fresh (and record error via `Hypercart_Logger::error()`)

These are small and align perfectly with “single write paths” + “triage-friendly debug”.

---

## 11. Self-Test UI + PHPUnit (UI + Terminal)

### Self Test UI (admin)

**Note:** Self Test is different from Manual Test Runner:
* **Manual Test Runner** (Section 8.1): Quick metrics check, no logging
* **Self Test**: Comprehensive system diagnostics

Add a “Run Self Test” button (nonce protected) that performs:

**System Checks:**
* ✅ Hypercart Helper installed and version check
* ✅ Cron schedule registered and next run exists
* ✅ JSON directory exists and is writable
* ✅ Log directory exists and is writable
* ✅ Lock acquire/release works
* ✅ Time functions work (`Hypercart_Time::now()`, `::format()`, etc.)
* ✅ Logger functions work (`Hypercart_Logger::info()`)

**Functional Checks:**
* ✅ Compute metrics once (calls all collectors)
* ✅ Validate scorer returns 0–100
* ✅ Validate JSON write/read cycle
* ✅ Validate log parsing (read today's log file)
* ✅ Optional: send a test email (checkbox to enable)

**Output results as a readable panel:**

* ✅/❌ per check with color coding
* Raw diagnostic messages
* Execution time for each check
* Copy-to-clipboard JSON snippet for support
* "Export Diagnostics" button (downloads full report as .txt file)

**Additional Diagnostics to Display:**
* PHP version and memory limit
* WordPress version
* Server OS and load average
* Disk space on WP installation
* WP timezone setting vs. server timezone
* WP-Cron status (disabled/enabled)
* File permissions on key directories

### PHPUnit tests (terminal)

Use WP’s PHPUnit framework (WP test suite) and run:

* `vendor/bin/phpunit`

Test categories:

1. **ScoringServiceTest**

   * Given known raw inputs, score matches expected
   * Out-of-range values clamp safely
2. **HealthRepositoryTest**

   * Pruning keeps only last 24h
   * Atomic write creates valid JSON
   * Corruption handling archives and rebuilds
3. **LockingTest**

   * Lock prevents overlap
   * TTL expiration works
4. **FsmTest**

   * Valid transitions succeed
   * Failures increment, trip activates
5. **SchedulerTest**

   * Interval registered
   * Event scheduled exactly once
6. **EmailReporterTest**

   * Formats subject/body correctly (mock wp_mail)

> Keep tests deterministic: abstract filesystem/time through small helper interfaces to allow stubbing.

---

## 12. Plugin UX (Settings link on Plugins page)

Add “Settings” action link:

* Use `plugin_action_links_{plugin_basename}` filter
* Link to the settings page slug

---

## 15. Security, Reliability, and Performance Considerations

* Admin pages restricted to capability like `manage_options`
* All admin actions nonce-protected (especially Manual Test Runner and Self Test)
* No raw file paths echoed without escaping
* JSON data file writes:

  * Ensure directory is within uploads and not web-browsable if possible (or add `.htaccess` where applicable)
  * Location: `wp-content/uploads/wp-server-monitor/health-data.json`
* Log file reading:
  * Only read files from `hypercart-logs/` directory
  * Sanitize file paths to prevent directory traversal
  * Limit file size reads (e.g., last 500 lines max)
  * Cache parsed log entries to avoid repeated file I/O
* Avoid expensive operations:

  * Keep JSON data file bounded (24h at 15-min intervals ≈ 96 samples, auto-pruned)
  * Log files managed by Hypercart_Logger (daily rotation, manual archiving)
  * AJAX endpoints return quickly (use caching where appropriate)

---

## 12. Additional Debugging Features (Visibility Without Over-Engineering)

### Recommended On-Screen Debugging Features

These features provide visibility without SSH access while avoiding over-engineering:

#### 1. **Quick Stats Dashboard Widget (Optional)**

Add a WordPress dashboard widget showing:
* Current health score (large, color-coded number)
* Last check time (local)
* Next check time (local)
* Quick status: "All systems normal" or "Issues detected"
* Link to full settings page

**Benefits:** At-a-glance status without navigating to settings page.

#### 2. **Admin Bar Indicator (Optional)**

Add a small indicator to the WordPress admin bar:
* Icon with color: Green (90+), Yellow (75-89), Orange (60-74), Red (<60)
* Hover shows: Score, last check time
* Click goes to settings page

**Benefits:** Always visible when logged in, non-intrusive.

#### 3. **Email Log Viewer**

Add a section showing recent emails sent:
* Read from log entries matching "Email sent" or "Email failed"
* Show: timestamp, recipient, subject line, status (sent/failed)
* Last 20 emails
* Filter by success/failure

**Benefits:** Verify emails are being sent without checking inbox.

#### 4. **Metric History Chart (Future Phase)**

Simple line chart showing:
* Combined score over last 24 hours
* Hover shows exact values
* Uses `Hypercart_Charts` (see Section 19)

**Benefits:** Visual trend analysis, spot patterns.

#### 5. **Export/Import Settings**

* Export current configuration as JSON
* Import configuration from JSON
* Useful for:
  * Backup before changes
  * Clone settings to another site
  * Support troubleshooting

#### 6. **"What's Being Saved" Preview**

In the Manual Test Runner section, add a toggle:
* "Show JSON Preview" checkbox
* When checked, displays the exact JSON structure that would be saved
* Formatted and syntax-highlighted
* Shows what goes into `health-data.json`

**Benefits:** See exactly what's being persisted without reading the file.

### What NOT to Build (Avoiding Over-Engineering)

❌ **Don't build:**
* Custom log rotation (use Hypercart_Logger's built-in)
* Complex alerting system (future phase)
* Real-time websocket updates (overkill for 15-min intervals)
* Custom database tables (JSON file is sufficient)
* Multi-site aggregation (out of scope)
* Historical data beyond 24h in JSON (use logs for history)

### Debugging Philosophy

**Goal:** Make the plugin "glass box" instead of "black box"
* ✅ Show what's happening in real-time
* ✅ Allow inspection without technical tools
* ✅ Provide actionable diagnostics
* ✅ Keep UI clean and organized
* ❌ Don't duplicate functionality
* ❌ Don't add features "just in case"

---

## 13. Plugin UX (Settings Link on Plugins Page)

Add "Settings" action link:

* Use `plugin_action_links_{plugin_basename}` filter
* Link to the settings page slug

---

## 14. Implementation Phases and Milestones

### Phase 0 — Dependency Setup (Day 1, first task)

* **Verify Hypercart Helper is installed and active**
* Implement dependency check (see section 19)
* Test `Hypercart_Time::now()` and `Hypercart_Logger::info()` calls
* Review `HH-INTEGRATION-GUIDE.md` thoroughly

Deliverable: Plugin loads only if Hypercart Helper is available.

### Phase 1 — Skeleton + FSM + Scheduling

* Plugin bootstrap with dependency check
* Options/state install/uninstall
* Scheduler interval and hook
* FSM-light state store
* Initial logging using `Hypercart_Logger`

Deliverable: cron runs a placeholder and updates state, logs to Hypercart logs.

### Phase 2 — Metrics + Scoring

* Implement collectors + fallbacks
* Implement scoring + unit tests

Deliverable: can compute score and raw metrics reliably.

### Phase 3 — JSON Repository (SOT) + Circuit Breaker

* Atomic persistence with `Hypercart_Time::utc_format()` timestamps
* 24h prune of JSON data file (keeps ~96 samples) using `Hypercart_Time::now()` for comparisons
* Lock + failure counter + tripped state
* Corruption recovery with `Hypercart_Logger::error()` logging
* **Note:** Only JSON data file is pruned; log files persist for manual archiving

Deliverable: stable file output with protections, all operations logged.

### Phase 4 — Admin Settings + Debug Panel + Visibility Features

* Settings page with multiple sections:
  * **Manual Test Runner** (AJAX endpoint, no logging)
  * **24-Hour Metrics Table** (read from JSON, local time display)
  * **Today's Log Viewer** (parse log file, filter by plugin, table display)
  * **Debug Panel** (FSM state, cron status, file info)
  * **"View File" button** (modal showing raw JSON)
* Time conversion using `Hypercart_Time::format()` for all displays
* Implement log file parser:
  * Use `Hypercart_Time::get_today_utc_date()` to find correct log file
  * Parse log format and extract wp-server-monitor entries
  * Display in table with local time conversion
* Add AJAX endpoints for Manual Test Runner

Deliverable: admin can see last 24h metrics, today's logs, and run manual tests without SSH access.

### Phase 5 — Email Reporting (with Score in Subject)

* Format email with score + raw metrics
* **Subject line includes composite score:** `Health Score: Good (82)`
* Timestamps using `Hypercart_Time::iso8601()` and `::format()`
* Configurable recipient
* Log email success/failure with `Hypercart_Logger`
* Ensure “write then email” ordering

Deliverable: mail every 15 minutes with score in subject line.

### Phase 6 — Self Test UI + PHPUnit polish

* Self Test UI
* Full PHPUnit suite runnable in terminal
* Docs and hardening

Deliverable: tested and maintainable baseline for expansion.

---

## 15. Acceptance Criteria

* **Dependency:** Hypercart Helper v1.0.0+ installed and active (checked on plugin load)
* Runs every 15 minutes (WP-Cron schedule visible in debug panel)
* Produces a combined score + three raw metrics
* Writes valid JSON in UTC with timestamps (using `Hypercart_Time::utc_format()`)
* Settings page displays last 24h in reverse chronological order
* Table columns: combined | raw | local time (using `Hypercart_Time::format()`)
* **Email subject includes composite score:** `[WP Server Monitor] Health Score: Good (82) — CPU 0.63 | MEM 71.2% | DISK 28.4%`
* Emails combined + raw metrics every run
* FSM state and debug panel show actionable triage info
* Circuit breaker prevents overlap/runaway and shows “tripped” status
* Self Test UI works and terminal PHPUnit tests pass
* “Settings” link appears in Plugins listing
* Code uses PHPDoc comments and clear separation of concerns
* **All time operations use `Hypercart_Time`** (no direct `time()` or `date()` calls)
* **All logging uses `Hypercart_Logger`** with plugin slug `'wp-server-monitor'`
* No separate log files created (uses centralized Hypercart logging)
* **NEW: Manual Test Runner works** (run metrics without logging, see live results)
* **NEW: Today's Log Viewer works** (display current day's log entries in table format)
  * Shows entries for wp-server-monitor plugin only
  * Displays in local time (converted from UTC log timestamps)
  * Filter by log level (all/errors/warnings)
* **NEW: Debug Panel includes "View File" button** (shows raw JSON in modal)
* **NEW: All timestamps displayed in WordPress configured timezone** (local time)

### Visibility Requirements (No SSH Required)
* ✅ Can view current metrics via Manual Test Runner
* ✅ Can view today's logs in admin UI
* ✅ Can view raw JSON data file in modal
* ✅ Can see what's being saved (JSON preview in Manual Test Runner)

---

## 17. File/Folder Layout (proposed)

```
wp-server-performance-monitor/
├─ wp-server-performance-monitor.php  # Main plugin file with dependency check
├─ readme.txt
├─ composer.json
├─ phpunit.xml.dist
├─ HH-INTEGRATION-GUIDE.md            # Hypercart Helper API reference (copy)
├─ src/
│  ├─ Admin/
│  │  ├─ SettingsPage.php            # Main settings page with all sections
│  │  ├─ ManualTestController.php    # AJAX endpoint for Manual Test Runner
│  │  ├─ LogViewerController.php     # Parse and display log files
│  │  └─ SelfTestController.php      # System diagnostics
│  ├─ Cron/
│  │  └─ Runner.php
│  ├─ Domain/
│  │  ├─ Fsm.php
│  │  ├─ FsmStateStore.php
│  │  └─ Models.php
│  ├─ Metrics/
│  │  ├─ MetricsCollectorInterface.php
│  │  ├─ CpuLoadCollector.php
│  │  ├─ MemoryCollector.php
│  │  └─ DiskCollector.php
│  ├─ Persistence/
│  │  └─ HealthRepository.php
│  ├─ Services/
│  │  ├─ ScoringService.php
│  │  ├─ SchedulerService.php
│  │  └─ EmailReporter.php
│  └─ Helpers/
│     ├─ FileHelper.php              # File operations, locking
│     ├─ LockHelper.php              # Mutex/lock management
│     ├─ LogParser.php               # Parse Hypercart log files (NEW)
│     └─ DebugHelper.php             # Debug utilities
│     # NOTE: TimeHelper removed - use Hypercart_Time instead
│     # NOTE: LogHelper removed - use Hypercart_Logger instead
└─ tests/
   ├─ ScoringServiceTest.php
   ├─ HealthRepositoryTest.php
   ├─ LockingTest.php
   ├─ FsmTest.php
   ├─ SchedulerTest.php
   └─ EmailReporterTest.php
```

**Important notes:**
* **NO TimeHelper** — use `Hypercart_Time` from Hypercart Helper
* **NO LogHelper** — use `Hypercart_Logger` from Hypercart Helper
* Include `HH-INTEGRATION-GUIDE.md` in plugin for reference

---

## 18. Testing Strategy

* Unit tests for math, pruning, and transitions
* Integration-ish tests for repository IO using temp dirs
* Mock WP functions where needed (or use WP test suite utilities)
* Keep time deterministic by injecting a “Clock” helper in core services

---

## 19. Future Extensibility

Because you’re using FSM-light + SOT JSON:

* Add thresholds + alert levels (warn/critical)
* Add richer metrics (DB query latency, PHP-FPM queue, Redis hit rate)
* Add charting in admin using `Hypercart_Charts` (see `HH-INTEGRATION-GUIDE.md`)
* Add webhook (Slack) notifications
* Add remote storage (S3) while still keeping local JSON as cache/SOT if desired

---

## 20. Hypercart Helper Integration

### Dependency Requirement

This plugin **REQUIRES** Hypercart Helper v1.0.0+ to be installed and active.

### Integration Points

#### Time Management (`Hypercart_Time`)

**CRITICAL:** Never use PHP's native time functions directly. Always use `Hypercart_Time`.

```php
// ✅ CORRECT
$now = Hypercart_Time::now();
$display = Hypercart_Time::format( 'Y-m-d H:i:s', $timestamp );
$utc_log = Hypercart_Time::utc_format( 'Y-m-d H:i:s', $timestamp );
$iso = Hypercart_Time::iso8601( $timestamp );

// ❌ WRONG - Never use these
$now = time();
$now = current_time( 'timestamp' );
$display = date( 'Y-m-d H:i:s', $timestamp );
```

**Usage in this plugin:**
* JSON timestamps: `Hypercart_Time::utc_format( 'c' )` (ISO 8601)
* Admin display: `Hypercart_Time::format( 'Y-m-d H:i:s' )`
* Email timestamps: `Hypercart_Time::iso8601()`
* Logging: `Hypercart_Time::utc_format( 'Y-m-d H:i:s' )`

#### Logging (`Hypercart_Logger`)

**CRITICAL:** Use centralized logging. Do NOT create separate log files.

```php
// ✅ CORRECT - Use Hypercart_Logger
Hypercart_Logger::info( 'wp-server-monitor', 'Metrics collected', array(
    'score' => 82,
    'cpu' => 0.63,
    'mem' => 71.2,
    'disk' => 28.4,
) );

Hypercart_Logger::warning( 'wp-server-monitor', 'High memory usage detected', array(
    'mem_pct' => 88.5,
    'threshold' => 85.0,
) );

Hypercart_Logger::error( 'wp-server-monitor', 'Failed to write JSON', array(
    'error' => $e->getMessage(),
    'file' => $file_path,
) );

// ❌ WRONG - Never create separate log files
error_log( 'message', 3, '/path/to/custom.log' );
file_put_contents( 'debug.log', $message, FILE_APPEND );
```

**Plugin slug:** `'wp-server-monitor'` (use consistently across all log calls)

**Log locations:**
* Directory: `WP_CONTENT_DIR/hypercart-logs/`
* Daily files: `hypercart-YYYY-MM-DD.log` (UTC dates)
* Format: `[YYYY-MM-DD HH:MM:SS UTC] wp-server-monitor LEVEL: Message {"context":"data"}`
* **Retention:** Log files persist indefinitely (no automatic deletion) for manual review/archiving
* **Accumulation:** Each 15-minute run appends to the current day's log file

**When to log:**
* `info()`: Successful operations (metrics collected, JSON written, email sent)
* `warning()`: Recoverable issues (high resource usage, metric collection fallback)
* `error()`: Failures (JSON write failed, circuit breaker tripped, email failed)
* `debug()`: Detailed diagnostics (only if WP_DEBUG enabled)

#### Dependency Check (Required)

Add this check in your main plugin file **before** any other code:

```php
/**
 * Check Hypercart Helper dependency
 */
function wp_server_monitor_check_dependencies() {
    if ( ! class_exists( 'Hypercart_Time' ) || ! class_exists( 'Hypercart_Logger' ) ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>WP Server Performance Monitor</strong> requires
                    <strong>Hypercart Helper v1.0.0+</strong> to be installed and active.
                    <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
                        Manage Plugins
                    </a>
                </p>
            </div>
            <?php
        } );
        return false;
    }

    // Check minimum version
    if ( defined( 'HYPERCART_HELPER_VERSION' ) &&
         version_compare( HYPERCART_HELPER_VERSION, '1.0.0', '<' ) ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>WP Server Performance Monitor</strong> requires
                    <strong>Hypercart Helper v1.0.0+</strong>
                    (found v<?php echo esc_html( HYPERCART_HELPER_VERSION ); ?>).
                </p>
            </div>
            <?php
        } );
        return false;
    }

    return true;
}

// Early check - don't load plugin if dependency missing
if ( ! wp_server_monitor_check_dependencies() ) {
    return;
}
```

#### Future: Charts Integration (`Hypercart_Charts`)

For Phase 2 charting features, use `Hypercart_Charts` (requires Helper v1.1.0+):

```php
// Example: render 24-hour health score chart
$chart = array(
    'id' => 'health-score-chart',
    'title' => 'Health Score (24 Hours)',
    'datasets' => array(
        array(
            'key' => 'score',
            'label' => 'Composite Score',
            'color' => '#2271b1',
            'points' => $chart_points, // array of ['x' => timestamp, 'y' => score]
        ),
    ),
);

echo Hypercart_Charts::render_canvas( $chart, array( 'height' => 300 ) );
```

See `HH-INTEGRATION-GUIDE.md` section "Hypercart_Charts API" for complete documentation.

### Complete API Reference

**Full documentation:** `HH-INTEGRATION-GUIDE.md`

**Key sections:**
* Dependency Check (lines 74-127)
* Hypercart_Time API (lines 131-280)
* Hypercart_Logger API (lines 284-391)
* Hypercart_Charts API (lines 395-574) — for future charting features

---

```
```
