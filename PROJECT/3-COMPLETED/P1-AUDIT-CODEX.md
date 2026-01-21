---
Project: Hypercart-Server-Monitor-MKII
Author: Codex
Date: 2026-01-16
Status: VERIFIED
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

# Verification Notes
- Verified atomic lock acquisition/release with token ownership. `src/Helpers/LockHelper.php`
- Verified repository-level `flock` around read+write and lockfile usage. `src/Persistence/HealthRepository.php`
- Verified admin UI uses `read(false)` to avoid repair/rename side effects. `src/Admin/AdminController.php`
- Verified state transitions occur under a state lock. `src/Domain/FsmStateStore.php`
- Verified scheduling is centralized in `SchedulerService`. `src/Services/SchedulerService.php`, `src/Plugin.php`

# Reliability (verified refactor)
- Lock acquisition uses atomic `add_option` with a per-run token, preventing concurrent acquisition. `src/Helpers/LockHelper.php`
- Lock release validates token ownership before deletion, preventing stale unlocks. `src/Helpers/LockHelper.php`
- JSON writes are serialized with a repository lock (`flock` on `health-data.lock`) around read+write. `src/Persistence/HealthRepository.php`
- Admin reads call `read(false)` to avoid repair/rename while cron may be writing. `src/Persistence/HealthRepository.php`, `src/Admin/AdminController.php`

# Maintainability (verified refactor)
- Cron scheduling is centralized in `SchedulerService` and used from activation/admin. `src/Plugin.php`, `src/Admin/AdminController.php`, `src/Services/SchedulerService.php`

# Best Practices (verified refactor)
- Implemented atomic lock primitives with token validation. `src/Helpers/LockHelper.php`
- Implemented a per-write repository lock (`flock` on a separate lockfile). `src/Persistence/HealthRepository.php`
- Separated “read” from “repair/rotate” via the `read($allow_repair)` flag used by admin paths. `src/Persistence/HealthRepository.php`, `src/Admin/AdminController.php`

# Performance Impact (post-refactor notes)
- Lock acquisition/release is now atomic and token-validated, reducing overlap and duplicate work. `src/Helpers/LockHelper.php`
- Admin reads avoid repair/rename, reducing filesystem contention with cron writes. `src/Persistence/HealthRepository.php`, `src/Admin/AdminController.php`

# Fixes and Impact
- F1 (completed): Implemented atomic lock with token validation (`add_option`) and token-checked release. Impact: prevents overlapping runs and stale-lock deletion. `src/Helpers/LockHelper.php`
- F2 (completed): Implemented repository lock via `flock` on `health-data.lock`. Impact: avoids dropped samples and temp-file collisions under concurrency. `src/Persistence/HealthRepository.php`
- F3 (completed): Split “read” from “repair/rotate” and use `read(false)` for admin UI. Impact: avoids mid-write renames and heavy I/O in admin views. `src/Persistence/HealthRepository.php`, `src/Admin/AdminController.php`
- F4 (completed): Added a state lock around state transitions. Impact: prevents lost updates when runs overlap. `src/Domain/FsmStateStore.php`, `src/Plugin.php`
- F5 (completed): Centralized scheduling paths via `SchedulerService`. Impact: reduces drift and scheduling inconsistencies. `src/Plugin.php`, `src/Admin/AdminController.php`, `src/Services/SchedulerService.php`
- F6 (completed): Consolidated metric collection into `MetricsService`. Impact: reduces duplicated logic and maintenance overhead. `src/Plugin.php`, `src/Admin/AdminController.php`, `src/Services/MetricsService.php`
