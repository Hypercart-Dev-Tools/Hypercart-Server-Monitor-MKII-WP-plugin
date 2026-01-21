**AUTHOR:** VSC CO-PILOT GPT 5.2
**STATUS:** DEFERRED
**DATE:** 2026-01-11

# Project Plan — Circuit Breaker / Runaway Protection (Emails, Logs, CRON)

## Table of Contents
1. [Purpose and Scope](#purpose-and-scope)
2. [Current Behavior (Observed)](#current-behavior-observed)
3. [Goals / Acceptance Criteria](#goals--acceptance-criteria)
4. [Non-Goals](#non-goals)
5. [Design Principles (Best Practices)](#design-principles-best-practices)
6. [Threat Model & Failure Modes](#threat-model--failure-modes)
7. [Proposed FSM-Centric Circuit Breaker Design](#proposed-fsm-centric-circuit-breaker-design)
8. [Centralized Helpers / DRY Plan](#centralized-helpers--dry-plan)
9. [Single Write Paths (State + JSON)](#single-write-paths-state--json)
10. [Email Runaway Protection Plan](#email-runaway-protection-plan)
11. [Log Runaway Protection Plan](#log-runaway-protection-plan)
12. [Operational UX (Admin Debug/Reset)](#operational-ux-admin-debugreset)
13. [Implementation Phases + Checklist](#implementation-phases--checklist)
14. [Test Plan](#test-plan)
15. [Rollout / Backwards Compatibility](#rollout--backwards-compatibility)

---

## High-level phased checklist (progress tracker)
> **NOTE FOR LLM/AGENT:** Keep this checklist up to date as work is completed. Mark items `[x]` as changes land, and keep the remaining steps actionable.

### Phase 0 — Audit + Confirmation
- [ ] Confirm current `Domain/FsmStateStore.php` behavior: threshold, cooldown, trip/restore rules
- [ ] Confirm `Helpers/LockHelper.php` semantics and timeout behavior
- [ ] Identify all email entry points (cron + admin test), and confirm frequency expectations
- [ ] Identify high-volume log sites (loops / repeated errors) and confirm logger capabilities (if any rate-limits exist)

### Phase 1 — Circuit Breaker Core (Run-level)
- [ ] Define canonical circuit breaker state schema (closed/open/half-open) owned by FSM state store
- [ ] Add deterministic “trip” rules (failure threshold within window)
- [ ] Add cooldown / recovery rules (half-open probe run)
- [ ] Ensure the breaker blocks *the run* early (before metric collection, JSON write, and email)

### Phase 2 — Email Throttling (Independent guard)
- [ ] Add a dedicated “email gate” (cooldown + per-run de-dupe)
- [ ] Ensure cron-run email sends are limited even if cron fires too frequently
- [ ] Ensure admin “Send Test Email” is rate-limited (admin-only, but still protect)

### Phase 3 — Log Noise Controls
- [ ] Add centralized log helper with structured context + optional de-dupe window
- [ ] Ensure repeated errors don’t spam logs in tight loops

### Phase 4 — Admin UX + Observability
- [ ] Add Debug tab fields: breaker state, failure count, cooldown remaining, last trip reason
- [ ] Add safe “Reset breaker” capability (nonce + `manage_options`)

### Phase 5 — Testing + Hardening
- [ ] Unit tests for breaker transitions + email throttling logic
- [ ] Integration test: repeated cron triggers → verify email not sent repeatedly
- [ ] Failure tests: JSON corruption, file permission failure → breaker trips and run aborts

---

## Purpose and Scope
This document converts the existing audit notes into an implementation-oriented project plan for **reliable runaway protection** in Hypercart Server Monitor MKII.

Primary focus:
- Prevent **runaway cron executions** from causing repeated heavy work.
- Prevent **runaway emails** (flooding inbox / SMTP throttling / site reputation harm).
- Prevent **runaway logs** (disk usage growth, noisy ops signals).

## Current Behavior (Observed)
From `src/Plugin.php`:
- The cron run checks FSM state at start:
  - It skips work if `$state_data['state'] === 'tripped'`.
- On exceptions it calls:
  - `$this->state_store->record_error( $e->getMessage() );`
  - `$this->state_store->transition_to( 'error' );`
- On success it calls:
  - `$this->state_store->reset_failures();`
- A lock is acquired before work:
  - `Helpers\LockHelper::acquire()`

**Implication:** There is some *run-level* runaway prevention (skip entire run if “tripped”), but it is not yet confirmed to be a full circuit breaker with thresholds, cooldown, and recovery.

## Goals / Acceptance Criteria
1. **Hard stop for runaway work:** when the breaker is open/tripped, the cron handler returns early (no metrics / no JSON writes / no email).
2. **Email flooding prevention (independent):** emails must not be sent repeatedly if cron triggers too often or replays.
3. **Log amplification prevention:** repeated failures must not flood logs (dedupe/rate limit at plugin level).
4. **FSM-centric:** breaker and guard states are visible and consistent via the state store; transitions are explicit.
5. **DRY + centralized helpers:** logic lives in a single place and is reused by cron and admin actions.
6. **Safe by default:** failures degrade gracefully with clear logs and debug visibility.

## Non-Goals
- Not building external alerting/escalation system (PagerDuty/Slack) in this phase.
- Not redesigning the JSON persistence format beyond what is needed for safe operation.

## Design Principles (Best Practices)
- **FSM is the source of truth** for operational state.
- **Single write paths**:
  - State is mutated only via `FsmStateStore`.
  - Health data is written only via `HealthRepository`.
- **Centralize policy**:
  - Circuit breaker rules live in one class/service.
  - Email throttling rules live in one class/service.
- **Fail closed for risky actions**:
  - If state cannot be read, prefer skipping email and heavy work rather than “best guessing”.
- **Structured logging** with stable keys; avoid concatenated strings.

## Threat Model & Failure Modes
### Threats / misconfigurations
- Multiple scheduled events created accidentally → cron runs far more than every 15 minutes.
- Site receives many anonymous hits triggering WP-Cron runner repeatedly.

### Failure modes
- JSON I/O failure (permissions, disk full).
- JSON corruption.
- Email transport failures (SMTP rate limits), causing repeated retry loops.
- Logging in repetitive loops causing disk churn/noise.

## Proposed FSM-Centric Circuit Breaker Design
### State model
Introduce a dedicated breaker concept with canonical states:
- **CLOSED**: normal operations.
- **OPEN**: run is blocked until cooldown expires.
- **HALF_OPEN**: allow one “probe” run to test recovery.

Store fields alongside FSM state (in `FsmStateStore` data):
- `breaker_state` (`closed|open|half_open`)
- `failure_count`
- `first_failure_utc` (or rolling window approach)
- `opened_at_utc`
- `cooldown_until_utc`
- `last_trip_reason`

### Trip rules (example policy; finalize after confirming store implementation)
- Increment failure_count on *hard failures* (JSON write failure, unexpected exception).
- Trip (OPEN) if failure_count ≥ N within M minutes.
- Cooldown for K minutes.
- After cooldown, transition to HALF_OPEN.
- In HALF_OPEN:
  - If probe succeeds → reset failures and return to CLOSED.
  - If probe fails → return to OPEN with extended cooldown (optional exponential backoff).

### Where the breaker is enforced
Enforce breaker check **immediately** in cron handler (before lock acquisition if lock is expensive; but ensure lock cannot prevent recovery).

## Centralized Helpers / DRY Plan
Add (or reuse if already present) centralized helpers/services:
- `Services/CircuitBreakerService`
  - `should_block_run(): bool`
  - `record_success()`
  - `record_failure( $reason, $context = [] )`
- `Services/EmailGateService`
  - `should_send_email( $sample ): bool`
  - `record_email_sent( $sample )`

All callsites (cron + admin) use these services rather than duplicating logic.

## Single Write Paths (State + JSON)
- Health data writes remain exclusively in `HealthRepository`.
- State transitions and breaker updates remain exclusively in `FsmStateStore` (or via the breaker service calling into it).
- Do not update options/transients from multiple places for the same concept.

## Email Runaway Protection Plan
Even with a run-level breaker, email can still flood if:
- cron runs too frequently and succeeds,
- or admin repeatedly triggers “test email”.

Add an independent email guard:
- A per-action **cooldown** (e.g., “at most 1 email per 15 minutes per site”).
- A per-sample **de-dupe** (e.g., if we already emailed for `ts_utc` / same score hash, skip).
- For admin test email:
  - separate cooldown (shorter ok) but still non-zero to prevent accidental hammering.

## Log Runaway Protection Plan
Implement a lightweight log de-dupe wrapper:
- A helper that takes `(level, message_key, context)`
- Optional: de-dupe window using a transient keyed by message_key (e.g., 60s)
- Always allow error logs through when state changes (trip/open/close), but suppress repetitive same-message spam.

## Operational UX (Admin Debug/Reset)
Make runaway protection visible and controllable:
- Debug tab:
  - breaker state, cooldown remaining, failure count
  - last error + last trip reason
  - email gate last sent time
- Admin action:
  - “Reset breaker” (nonce + capability)
  - optional “Clear email cooldown” for testing (guarded)

## Implementation Phases + Checklist
### Phase 0 — Audit + Confirmation
- Confirm actual `FsmStateStore` storage schema and keys
- Confirm what “tripped” means today (or if it’s unused/partial)

### Phase 1 — Circuit Breaker Core
- Implement canonical breaker state + transitions
- Enforce early block in cron run

### Phase 2 — Email Guard
- Implement cooldown + dedupe
- Ensure both cron email and admin test email use the same gate

### Phase 3 — Log De-dupe
- Centralize logging calls for repeated failure pathways

### Phase 4 — Admin UX
- Surface breaker + email gate status
- Add reset actions

### Phase 5 — Tests
- Unit tests for trip/cooldown/half-open
- Integration test for repeated triggers

## Test Plan
- **Unit**: breaker state transitions (closed→open, open→half-open, half-open→closed/open)
- **Unit**: email gate dedupe + cooldown
- **Integration**: simulate rapid cron triggers → verify only one email sent and/or run blocked
- **Integration**: force JSON write failure → verify breaker opens and subsequent runs block

## Rollout / Backwards Compatibility
- Default behavior should remain: “monitor every 15 minutes, email after write”.
- New guards should be conservative:
  - if state cannot be read reliably, skip email rather than risk runaway.
- Provide a clear changelog note for operators (debug tab + logs).