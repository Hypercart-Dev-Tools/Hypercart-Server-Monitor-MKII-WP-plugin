---
Project: Hypercart-Server-Monitor-MKII
Author: Codex
Date: 2026-01-16
Status: IN PROGRESS
Priority: P1
Goal: Audit for reliability, maintainability, and best-practice adherence
---

# High-Level Project Checklist

## Phase 1: Concurrency and Locking
- [x] F1: Implement an atomic lock with token validation on release to prevent overlap and stale lock deletion.

## Phase 2: Persistence Safety
- [x] F2: Add a repository lock or append/rotate strategy to prevent dropped samples and temp-file collisions.
- [x] F3: Split read-only access from archive/repair behavior to avoid mid-write renames.

## Phase 3: State and Scheduling Consistency
- [x] F4: Add a state transition guard (version or compare-and-swap) to prevent lost updates.
- [x] F5: Centralize scheduling paths (activation/admin) to avoid drift.

## Phase 4: DRY and Reuse
- [x] F6: Consolidate metric collection into a shared service.

# Reliability
- Lock acquisition is non-atomic (check-then-set transient). Overlapping cron runs can both acquire the lock, leading to duplicate writes/emails and inconsistent state. `src/Helpers/LockHelper.php`
- Lock release is non-owned. If a run exceeds TTL and another run re-acquires, the earlier run can delete the newer lock, allowing overlap. `src/Helpers/LockHelper.php`
- JSON persistence is read-modify-write without a repository-level mutex; concurrent writes can drop samples or clobber the shared temp file. `src/Persistence/HealthRepository.php`
- Admin “read” path can rename/archive the JSON file on size/corruption while cron is writing, causing mid-write file moves. `src/Persistence/HealthRepository.php`, `src/Admin/AdminController.php`

# Maintainability
- Metric collection logic duplicated between cron run and admin manual test; a shared service would reduce drift and simplify future changes. `src/Plugin.php`, `src/Admin/AdminController.php`
- Cron scheduling logic appears in multiple call sites (activation and admin AJAX), making it easier to introduce inconsistencies when updating scheduling behavior. `src/Plugin.php`, `src/Admin/AdminController.php`, `src/Services/SchedulerService.php`

# Best Practices
- Consider atomic lock primitives (`add_option`/`wp_cache_add` with token) to avoid races; transient check-then-set is not safe under concurrency. `src/Helpers/LockHelper.php`
- Consider a per-write file lock (e.g., `flock` on a separate lockfile) around `read`+`write` to guard against concurrent writers. `src/Persistence/HealthRepository.php`
- Consider separating “read” from “repair/rotate” so UI reads do not mutate/rename storage while cron writes are in progress. `src/Persistence/HealthRepository.php`

# Performance Impact
- The non-atomic lock doubles the transient I/O (read then write) and increases the chance of concurrent runs, compounding CPU and I/O load. `src/Helpers/LockHelper.php`
- Every sample write reads/parses the full JSON file, then rewrites it; as the file grows this increases runtime and time spent in the critical section. `src/Persistence/HealthRepository.php`
- Admin reads that can archive/rename add blocking filesystem work on what should be a cheap read, increasing contention with cron writes. `src/Persistence/HealthRepository.php`, `src/Admin/AdminController.php`

# Fixes and Impact
- F1 (completed): Implement an atomic lock with a token (`add_option`/`wp_cache_add`) and verify token on release. Impact: prevents overlapping runs and stale-lock deletion, reducing duplicate work. `src/Helpers/LockHelper.php`
- F2 (completed): Add a repository lock (e.g., `flock` on a dedicated lockfile) or an append/rotate strategy. Impact: avoids dropped samples and temp-file collisions under concurrency. `src/Persistence/HealthRepository.php`
- F3 (completed): Split “read” from “repair/rotate” and run repair only in the cron path. Impact: removes heavy I/O from admin views and avoids mid-write renames. `src/Persistence/HealthRepository.php`, `src/Admin/AdminController.php`
- F4 (completed): Add state transition guards (version field or compare-and-swap). Impact: prevents lost updates when runs overlap. `src/Domain/FsmStateStore.php`, `src/Plugin.php`
- F5 (completed): Centralize scheduling paths to a single entry point. Impact: reduces drift and hard-to-trace scheduling inconsistencies. `src/Plugin.php`, `src/Admin/AdminController.php`, `src/Services/SchedulerService.php`
- F6 (completed): Consolidate metric collection into a shared service. Impact: reduces duplicated logic and maintenance overhead. `src/Plugin.php`, `src/Admin/AdminController.php`, `src/Services/MetricsService.php`
