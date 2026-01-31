2026-01-30
==========

- [x] 1. State transitions can silently fail: transition_to() returns false when the state lock can’t be acquired, but callers in the main run flow don’t check the return value. That means the run can proceed while state stays stale. Plugin.php

Severity: Medium
**Status**: Fixed in v0.4.19 - Added return value checks and logging for all transition_to() calls

- [x] 2. Probe error path ignores lock failure: record_probe_failure() can return false (lock not acquired), but the exception path doesn’t fall back to transition_to('error'). If the lock is stuck, you can lose the failure/trip record entirely. Plugin.php and FsmStateStore.php

Severity: Medium
**Status**: Fixed in v0.4.19 - Added fallback to transition_to('error') when record_probe_failure() returns false

- [ ] 3. record_probe_success() doesn’t change state: it only clears counters. In current usage it’s called after transition_to('completed'), so you’re fine, but it’s easy to misuse later and leave state as tripped/half_open with cleared counters. FsmStateStore.php

Severity: Low

- [ ] 4. run_breaker_self_test() restores via update_option() directly (no validation/logs/updated_utc). It’s under lock so probably fine, but it bypasses the normal transition path. FsmStateStore.php

Severity: Low


2026-01-27
==========

All backlog items from 2026-01-18 have been completed and are documented in `CHANGELOG.md` (versions 0.4.3–0.4.7 and 0.4.7–0.4.13).

This backlog has been trimmed to reduce file size and is ready for new work items.

-[x] Add timeouts to the jQuery $.ajax calls and handle timeout-specific errors.

-[x] Replace document.execCommand('copy') with the async Clipboard API with graceful fallback.

-[x] Tidy the FSM self-test to reuse a single $data snapshot within the lock.
