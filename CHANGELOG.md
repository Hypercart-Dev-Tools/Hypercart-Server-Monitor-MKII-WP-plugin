# Changelog

All notable changes to Hypercart Server Monitor MKII will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-01-10

### Added - First Iteration (Phases 0-3)

#### Phase 0: Dependency Setup & Project Structure
- Main plugin file with dependency check for Hypercart Helper v1.0.0+
- Admin notices for missing or outdated Hypercart Helper
- Plugin activation/deactivation hooks
- Basic plugin structure and namespacing

#### Phase 1: Core Infrastructure (FSM + Scheduling)
- `Plugin` class with singleton pattern
- `FsmStateStore` for state management with transitions
  - States: idle, scheduled, running, writing, emailing, completed, error, tripped
  - Failure counter and circuit breaker logic
  - Last error tracking
- `SchedulerService` for WP-Cron integration
  - Custom schedule: every 15 minutes
  - Schedule/unschedule methods
  - Status reporting for debugging
- Integration with `Hypercart_Logger` for all logging
- Settings link on plugins page

#### Phase 2: Metrics Collection & Scoring
- `MetricsCollectorInterface` for standardized collectors
- `CpuLoadCollector` - 1-minute load average normalized by CPU cores
  - Fallback to 1 core if detection fails
  - Support for `sys_getloadavg()` and `/proc/cpuinfo`
- `MemoryCollector` - Memory usage percentage from `/proc/meminfo`
  - Calculates used percentage from MemTotal and MemAvailable
  - Graceful fallback if unsupported
- `DiskCollector` - Disk free space percentage
  - Checks WP_CONTENT_DIR or ABSPATH
  - Uses `disk_free_space()` and `disk_total_space()`
- `ScoringService` - Converts raw metrics to 0-100 score
  - CPU scoring: 100 at ≤0.7, 0 at >2.0
  - Memory scoring: 100 at ≤70%, 0 at >95%
  - Disk scoring: 100 at ≥20%, 0 at <5%
  - Weighted average: CPU 40%, Memory 35%, Disk 25%
  - Score labels: Excellent (90+), Good (75-89), Warning (60-74), Critical (<60)

#### Phase 3: JSON Repository & Circuit Breaker
- `HealthRepository` for JSON persistence
  - Atomic writes using temp file + rename
  - 24-hour auto-pruning (keeps ~96 samples)
  - Corruption detection and archiving
  - Directory creation with .htaccess protection
  - File status reporting for debugging
- `LockHelper` for mutex locking
  - WordPress transient-based locking
  - 10-minute TTL to prevent deadlock
  - Lock status reporting
- Full monitoring workflow in `Plugin::run_monitoring()`
  - Circuit breaker check (skip if tripped)
  - Lock acquisition (skip if locked)
  - Metric collection
  - Score calculation
  - JSON persistence
  - State transitions (scheduled → running → writing → completed)
  - Error handling with failure counter
  - Lock release in finally block

### Technical Details
- **Plugin Name:** `Hypercart Server Monitor MKII`
- **Plugin Slug:** `hypercart-server-monitor`
- **Namespace:** `Hypercart_Server_Monitor`
- **Text Domain:** `hypercart-server-monitor`
- **JSON File:** `wp-content/uploads/hypercart-server-monitor/health-data.json`
- **Log Files:** `wp-content/hypercart-logs/hypercart-YYYY-MM-DD.log`
- **Cron Hook:** `hypercart_server_monitor_run`
- **Cron Interval:** `every_15_minutes` (900 seconds)
- **State Option:** `hypercart_server_monitor_state`
- **Lock Transient:** `hypercart_server_monitor_lock`

### Dependencies
- WordPress 5.8+
- PHP 7.4+
- Hypercart Helper 1.0.0+

### File Structure
```
wp-server-performance-monitor/
├── wp-server-performance-monitor.php  # Main plugin file
├── readme.txt                         # WordPress plugin readme
├── CHANGELOG.md                       # This file
├── README.md                          # Project plan
├── HH-INTEGRATION-GUIDE.md           # Hypercart Helper API docs
├── DEBUGGING-FEATURES-SUMMARY.md     # Debugging features guide
└── src/
    ├── Plugin.php                     # Main plugin class
    ├── Domain/
    │   └── FsmStateStore.php         # State management
    ├── Metrics/
    │   ├── MetricsCollectorInterface.php
    │   ├── CpuLoadCollector.php
    │   ├── MemoryCollector.php
    │   └── DiskCollector.php
    ├── Persistence/
    │   └── HealthRepository.php      # JSON storage
    ├── Services/
    │   ├── ScoringService.php        # Metric scoring
    │   └── SchedulerService.php      # Cron management
    └── Helpers/
        └── LockHelper.php            # Mutex locking
```

### Known Limitations (To Be Addressed in Future Iterations)
- No admin UI yet (coming in Phase 4)
- No email reporting yet (coming in Phase 5)
- No manual test runner yet (coming in Phase 4)
- No log viewer yet (coming in Phase 4)
- Memory collector only works on Linux (requires `/proc/meminfo`)
- CPU core detection may fallback to 1 on some systems

### Next Iteration (Planned)
- Phase 4: Admin UI with Manual Test Runner, Metrics Table, Log Viewer, Debug Panel
- Phase 5: Email reporting with score in subject line
- Phase 6: Self-test UI and PHPUnit tests

---

## [Unreleased]

### Planned Features
- Admin dashboard with real-time metrics
- Manual test runner (run without logging)
- Today's log viewer (parse and display logs)
- Debug panel with file inspection
- Email notifications with score in subject
- Self-test diagnostics
- Export/import settings
- Dashboard widget (optional)
- Admin bar indicator (optional)

---

**Version:** 0.1.0  
**Date:** 2026-01-10  
**Status:** First iteration complete (Phases 0-3)

