2026-01-18
==========

**Circuit Breaker Improvements**
**STATUS:** IN PROGRESS

- [x]Early Breaker Check: Add a check at the start of each run (before metrics, JSON write, email) to abort if the FSM state is tripped.

- [x]Cooldown/Recovery: Implement a cooldown period and half-open probe logic to allow recovery attempts after a trip.

- [x]Benchmark Timeout: Add a max execution time for benchmarks to prevent runaway CPU usage.

- [x]Centralize Breaker Logic: Use FSM state as the canonical source for breaker status.

- [x]Logging: Log all breaker trips and recovery attempts for auditability.

Notes: Breaker gating runs through the FSM store, with a cooldown window and a single probe run in `half_open`. Probe success closes the breaker and resets failures, while probe failure re-trips with cooldown. Benchmarks now enforce a timeout with a filter override for slow environments.

**Shortcode Raadonly View** 
**STATUS:** NOT STARTED

Print Table "repeating" (echoing?) the Dashboard onto front end WP page but with proper security restricitons.

User can 
