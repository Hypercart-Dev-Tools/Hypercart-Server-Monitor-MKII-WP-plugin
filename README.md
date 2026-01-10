````md
# WP Server Performance Monitor — Project Plan

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
12. Plugin UX (Settings Link on Plugins Page)  
13. Security, Reliability, and Performance Considerations  
14. Implementation Phases and Milestones  
15. Acceptance Criteria  
16. File/Folder Layout  
17. Testing Strategy  
18. Future Extensibility

---

## Actionable Checklist (start here)
- [ ] Confirm where the JSON file will live (recommended: `wp-content/uploads/wp-server-monitor/health.json`)
- [ ] Confirm helper plugin API for UTC→local conversion (function name / filter signature)
- [ ] Implement metric collectors (Load, Memory, Disk) with safe fallbacks
- [ ] Implement scoring (0–100), weights, and thresholds
- [ ] Implement JSON repository with atomic writes + 24h pruning
- [ ] Implement FSM-light state store + transitions + last error
- [ ] Implement scheduler (WP-Cron) + admin-visible CRON status
- [ ] Implement email reporter (wp_mail) every run
- [ ] Add circuit breaker: lock, rate limit, failure counter, file size cap, and backoff
- [ ] Build Settings page: table (combined | raw | local time) + Debug panel
- [ ] Add “Self Test” section/page: run checks from admin UI (nonce protected)
- [ ] Add PHPUnit tests runnable in terminal + minimal mocks/stubs
- [ ] Add plugin action link “Settings” on Plugins listing
- [ ] Add PHPDoc across public APIs and major internals
- [ ] Final pass: DRY helpers, single write paths, single SOT, logging/errors

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
- JSON persistence (24h rolling window)
- WP Admin settings page:
  - Table view
  - Debug panel (FSM state, CRON status, etc.)
- Email notification every 15 minutes
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

### JSON file characteristics
- **UTC timestamps** (ISO8601, e.g. `2026-01-10T23:45:00Z`)
- Stores last **24 hours** only (rolling window)
- Reverse chronological display in UI (but stored order can be chronological for easier pruning)

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

* Read JSON once, prune, append, write once per run.
* Write atomically:

  1. Write to `health.json.tmp`
  2. `rename()` to `health.json` (atomic on same filesystem)
* Apply file permissions and directory creation once via installer.

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

  1. **Latest Samples Table** (last 24h, newest first)
  2. **Debug Panel** (always visible initially)
  3. **Self Test** (button + results)
  4. **Settings** (email recipient, enable/disable emailing, etc.)

### Table columns (left → right)

1. **Combined Score** (0–100)
2. **Raw Metrics** (compact string or stacked lines)

   * e.g. `CPU: 0.63 | MEM: 71.2% | DISK: 28.4%`
3. **Local Time**

   * Convert from `ts_utc` using your helper plugin API

### Reverse chronological display

* Repository returns samples sorted by `ts_utc desc` (or UI sorts after load)

### Debug panel (initial)

Show at minimum:

* Current FSM state + last transition time (UTC)
* Last run UTC + duration
* Next scheduled run time
* Whether cron event exists
* Lock status (locked/unlocked, lock age)
* Circuit breaker status (failure count, tripped until)
* JSON file status (path, writable, size, sample count)
* Last error message + timestamp (if any)

> Later: add WP config flag to hide debug panel; for now always on.

---

## 9. Email Reporting

### Email content (every run)

Subject example:

* `[WP Server Monitor] Score 82 (CPU 0.63 | MEM 71.2% | DISK 28.4%)`

Body includes:

* UTC timestamp and local timestamp
* Combined score
* Raw metrics
* Any warnings (e.g. unsupported metric collector fallback)

Implementation notes:

* Use `wp_mail()`
* Recipient configurable (default: site admin email)
* Ensure email sending happens after JSON write (so the JSON remains SOT)

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

   * If JSON invalid: archive to `health.json.bad.<timestamp>` and start fresh (and record error)

These are small and align perfectly with “single write paths” + “triage-friendly debug”.

---

## 11. Self-Test UI + PHPUnit (UI + Terminal)

### Self Test UI (admin)

Add a “Run Self Test” button (nonce protected) that performs:

* Check cron schedule registered and next run exists
* Check JSON directory exists and is writable
* Check lock acquire/release works
* Compute metrics once (without persisting, or persist to a separate `selftest.json`)
* Validate scorer returns 0–100
* Validate time conversion helper is callable
* Optional: send a test email (configurable toggle)

Output results as a readable panel:

* ✅/❌ per check
* Raw diagnostic messages
* Copy-to-clipboard JSON snippet for support

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

## 13. Security, Reliability, and Performance Considerations

* Admin pages restricted to capability like `manage_options`
* All admin actions nonce-protected
* No raw file paths echoed without escaping
* JSON writes:

  * Ensure directory is within uploads and not web-browsable if possible (or add `.htaccess` where applicable)
* Avoid expensive operations:

  * No large logs
  * Prune in-memory and keep sample count bounded (24h at 15-min intervals ≈ 96 samples)

---

## 14. Implementation Phases and Milestones

### Phase 1 — Skeleton + FSM + Scheduling

* Plugin bootstrap
* Options/state install/uninstall
* Scheduler interval and hook
* FSM-light state store

Deliverable: cron runs a placeholder and updates state.

### Phase 2 — Metrics + Scoring

* Implement collectors + fallbacks
* Implement scoring + unit tests

Deliverable: can compute score and raw metrics reliably.

### Phase 3 — JSON Repository (SOT) + Circuit Breaker

* Atomic persistence
* 24h prune
* Lock + failure counter + tripped state
* Corruption recovery

Deliverable: stable file output with protections.

### Phase 4 — Admin Settings + Debug Panel

* Settings page with table + debug panel
* Time conversion using helper plugin
* Read from JSON only

Deliverable: admin can see last 24h and triage status.

### Phase 5 — Email Reporting

* Format email with score + raw metrics
* Configurable recipient
* Ensure “write then email” ordering

Deliverable: mail every 15 minutes.

### Phase 6 — Self Test UI + PHPUnit polish

* Self Test UI
* Full PHPUnit suite runnable in terminal
* Docs and hardening

Deliverable: tested and maintainable baseline for expansion.

---

## 15. Acceptance Criteria

* Runs every 15 minutes (WP-Cron schedule visible in debug panel)
* Produces a combined score + three raw metrics
* Writes valid JSON in UTC with timestamps
* Settings page displays last 24h in reverse chronological order
* Table columns: combined | raw | local time (via helper)
* Emails combined + raw metrics every run
* FSM state and debug panel show actionable triage info
* Circuit breaker prevents overlap/runaway and shows “tripped” status
* Self Test UI works and terminal PHPUnit tests pass
* “Settings” link appears in Plugins listing
* Code uses PHPDoc comments and clear separation of concerns

---

## 16. File/Folder Layout (proposed)

```
wp-server-performance-monitor/
├─ wp-server-performance-monitor.php
├─ readme.txt
├─ composer.json
├─ phpunit.xml.dist
├─ src/
│  ├─ Admin/
│  │  ├─ SettingsPage.php
│  │  └─ SelfTestController.php
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
│     ├─ TimeHelper.php
│     ├─ FileHelper.php
│     ├─ LockHelper.php
│     └─ DebugHelper.php
└─ tests/
   ├─ ScoringServiceTest.php
   ├─ HealthRepositoryTest.php
   ├─ LockingTest.php
   ├─ FsmTest.php
   ├─ SchedulerTest.php
   └─ EmailReporterTest.php
```

---

## 17. Testing Strategy

* Unit tests for math, pruning, and transitions
* Integration-ish tests for repository IO using temp dirs
* Mock WP functions where needed (or use WP test suite utilities)
* Keep time deterministic by injecting a “Clock” helper in core services

---

## 18. Future Extensibility

Because you’re using FSM-light + SOT JSON:

* Add thresholds + alert levels (warn/critical)
* Add richer metrics (DB query latency, PHP-FPM queue, Redis hit rate)
* Add charting in admin (sparklines)
* Add webhook (Slack) notifications
* Add remote storage (S3) while still keeping local JSON as cache/SOT if desired

---

```
```
