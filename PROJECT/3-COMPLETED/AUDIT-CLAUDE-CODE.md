Security & Performance Audit — v0.4.28
=======================================

Audited by: Claude Code
Date: 2026-03-11

## Security Review

### Medium Severity

#### 1. CSS Injection via font settings — FIXED in v0.4.28
**Files:** AdminController.php, Plugin.php

`sanitize_text_field()` was used for font weight values, which doesn't prevent CSS injection. A value like `400} .wp-admin{display:none` would pass sanitization and be interpolated directly into the CSS string. This CSS is also rendered on the public frontend via the shortcode.

**Fix applied:**
- Added `sanitize_font_weight()` method with strict allowlist (`100`–`900`) in both AdminController and Plugin.
- Added defense-in-depth: all font settings (sizes, weights, colors) are re-sanitized at render time in `get_custom_font_css()`.
- Font weight is validated on save (`save_font_settings()`) AND on render.

#### 2. DOM-based XSS patterns in admin.js — FIXED in v0.4.28
**File:** assets/admin.js

Several places used `.html()` to inject server response data without escaping:
- `response.last_run` injected directly
- `error` variable injected directly
- `steps[i].step` and `response.data.message` injected via `.html()`
- AJAX response messages injected via `.html()`

**Fix applied:**
- All `.html()` calls replaced with safe DOM construction: `$('<span>').text()`, `document.createTextNode()`, `.empty().append()`.
- Cron health check, email toggle feedback, and breaker self-test results all use text-safe methods now.

#### 3. Non-atomic lock acquisition — OPEN (accepted risk)
**Files:** LockHelper.php:51-59, FsmStateStore.php:549-558

The lock pattern is: check expired → `delete_option` → `add_option`. Between the delete and add, another process could acquire the lock. This is a TOCTOU race condition. Under high concurrency (e.g., multiple cron runners), two processes could run monitoring simultaneously.

**Status:** Accepted risk. WordPress options table doesn't support true atomic compare-and-swap. The window is very small and the worst case (two benchmarks run simultaneously) is benign — it would just produce a duplicate sample. A filesystem flock or MySQL `GET_LOCK()` would fix this but adds complexity disproportionate to the risk.

### Low Severity

#### 4. Public information disclosure — OPEN (by design)
**File:** Plugin.php:136-144

The `/wp-json/cron-health/v1/status` endpoint is fully public (`__return_true`). It exposes the last cron run timestamp, which reveals server activity patterns. The rate limiting uses `$_SERVER['REMOTE_ADDR']` which is unreliable behind proxies/CDNs.

**Status:** By design — the endpoint exists for external uptime monitors (e.g., UptimeRobot). The information disclosed (healthy/unhealthy + timestamp) is minimal. Rate limiting is best-effort. If needed, the endpoint can be restricted via the `permission_callback` or a filter.

#### 5. Nginx not protected — OPEN (documentation item)
**File:** HealthRepository.php:84-101

The `.htaccess` protection only works on Apache. On Nginx, the `health-data.json` file in `wp-content/uploads/` would be publicly accessible. No Nginx-equivalent protection is added.

**Status:** Open. This is a documentation/deployment concern. The JSON file contains only benchmark timing data (no credentials or PII). A note in README-SECURITY.md about adding Nginx `location` rules would address this. WordPress cannot write Nginx config at runtime.

---

## Performance Review

#### 6. No caching on frontend shortcode — FIXED in v0.4.28
**File:** Plugin.php:326-338

Every frontend page view with the shortcode created a new `HealthRepository` and read the full JSON file from disk. On high-traffic pages, this means `file_get_contents` + `json_decode` on every request.

**Fix applied:**
- Added transient cache with 30-second default TTL (filterable via `hypercart_server_monitor_frontend_shortcode_cache_ttl`).
- Cache is invalidated (`delete_transient`) immediately after a new sample is written in `run_monitoring_internal()`.
- Multisite-aware cache key via `get_frontend_health_data_transient_key()`.
- TTL of 0 disables caching entirely.

#### 7. Benchmark CPU cost every 15 minutes — OPEN (by design)
**File:** BenchmarkCollector.php:47-69

Three iterations of 50k math ops + 10k string ops + 10k array ops every 15 minutes. On shared hosting under load, this could noticeably spike CPU. The 10-second timeout is a good safeguard, but the benchmark itself adds load to the very server it's measuring.

**Status:** By design — the benchmark is the core feature. The load is brief (typically 50–200ms total) and bounded by the 10s timeout. The 15-minute interval is configurable via cron schedule. The circuit breaker prevents runaway execution if benchmarks consistently fail.

---

## Summary

| # | Finding                        | Severity | Status              |
|---|--------------------------------|----------|---------------------|
| 1 | CSS injection via font weights | Medium   | FIXED in v0.4.28    |
| 2 | DOM XSS in admin.js            | Medium   | FIXED in v0.4.28    |
| 3 | Non-atomic lock acquisition    | Medium   | Accepted risk       |
| 4 | Public info disclosure (REST)  | Low      | By design           |
| 5 | Nginx directory protection     | Low      | Open (docs item)    |
| 6 | No frontend shortcode cache    | Perf     | FIXED in v0.4.28    |
| 7 | Benchmark CPU cost             | Perf     | By design           |

**Overall:** No critical vulnerabilities. The codebase is well-structured with proper nonce verification, capability checks, output escaping (`esc_html`, `esc_attr`), file size limits, and circuit breaker protection. The three actionable fixes (CSS injection, DOM XSS, frontend caching) have been applied in v0.4.28.
