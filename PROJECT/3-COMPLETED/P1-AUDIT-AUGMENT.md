### Security & Performance Audit Report

## Project Overview
**Hypercart Server Monitor MKII** (v0.4.13) - A WordPress plugin that monitors server health via synthetic PHP benchmarks every 15 minutes, with email alerts and an admin dashboard.

---

## 🔴 SECURITY RED FLAGS

### 1. **Public REST API Endpoint Without Rate Limiting** (Medium Risk)
**Location:** `src/Plugin.php` lines 130-139

```php
'permission_callback' => '__return_true',
```

	The cron health endpoint `/wp-json/cron-health/v1/status` is completely public with no rate limiting. While it only returns status information, it could be:
- Used for reconnaissance (reveals cron health status)
- Abused for DoS via repeated requests

	**Recommendation:** Add rate limiting or consider requiring authentication for external monitoring tools via API keys.
	
	STATUS: Resolved in v0.4.14 via per-IP rate limiting (default 6 requests per 5 minutes, filterable) on `/wp-json/cron-health/v1/status` returning HTTP 429 when exceeded.

---

### 2. **Potential Information Disclosure in Error Messages** (Low Risk)
**Location:** `src/Plugin.php` lines 462-469

```php
\Hypercart_Logger::error(
    'hypercart-server-monitor',
    $options['context_label'] . ' failed',
    array(
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),  // Full stack trace logged
    )
);
```

Full stack traces are logged which could expose file paths and internal structure if logs are accessible.

**Recommendation:** Consider sanitizing stack traces in production or using a log level filter.

---

### 3. **Deprecated `document.execCommand('copy')` Usage** (Low Risk)
**Location:** `assets/admin.js` lines 58-59, 82-83

```javascript
document.execCommand('copy');
```

This API is deprecated and may not work in all browsers. While not a security issue per se, it could fail silently.

**Recommendation:** Use the modern Clipboard API (`navigator.clipboard.writeText()`).

---

### 4. **No CSRF Protection on REST Endpoint** (Low Risk)
**Location:** `src/Plugin.php` lines 130-139

The REST endpoint uses `'permission_callback' => '__return_true'` which bypasses all authentication. While it's read-only, best practice is to use nonces for any endpoint that could be called from the admin context.

---

## 🟡 POTENTIAL SECURITY CONCERNS (Already Mitigated)

### ✅ Log File Allowlist - PROPERLY IMPLEMENTED
**Location:** `src/Admin/AdminController.php` lines 270-281

The log viewer correctly validates against an allowlist:
```php
if ( $selected_file && ! in_array( $selected_file, $log_files, true ) ) {
    \Hypercart_Logger::warning(...);
    $selected_file = null; // Reject invalid selection.
}
```

### ✅ XSS Prevention in JavaScript - PROPERLY IMPLEMENTED
**Location:** `src/Admin/views/tab-manual-test.php` lines 60-69

The `escapeHtml()` function is properly implemented and used for dynamic content.

### ✅ Nonce Verification - PROPERLY IMPLEMENTED
All AJAX handlers use `check_ajax_referer()` with proper nonce verification.

### ✅ Capability Checks - PROPERLY IMPLEMENTED
All admin functions check `current_user_can('manage_options')`.

### ✅ File Size Limit - PROPERLY IMPLEMENTED
**Location:** `src/Persistence/HealthRepository.php` lines 139-165

1MB file size limit prevents memory exhaustion attacks.

### ✅ Atomic File Writes - PROPERLY IMPLEMENTED
**Location:** `src/Persistence/HealthRepository.php` lines 201-255

Uses temp file + rename pattern for atomic writes.

### ✅ Directory Protection - PROPERLY IMPLEMENTED
**Location:** `src/Persistence/HealthRepository.php` lines 83-115

Creates `.htaccess` and `index.html` for defense-in-depth.

---

## 🟠 NON-PERFORMANT PATTERNS

### 1. **Repeated `get_option()` Calls in State Lock Operations** (Medium Impact)
**Location:** `src/Domain/FsmStateStore.php`

The `with_state_lock()` pattern calls `get_option()` and `update_option()` multiple times within a single operation. Each call is a database query.

**Example in `run_breaker_self_test()`:** Lines 324-413 make ~10+ database calls.

**Recommendation:** Consider caching the state data within the lock scope and only writing once at the end.

---

### 2. **Synchronous Benchmark Execution Blocks Request** (Medium Impact)
**Location:** `src/Metrics/BenchmarkCollector.php` lines 47-69

The benchmark runs 3 iterations of CPU-intensive operations synchronously. While there's a timeout (10 seconds), this blocks the PHP process.

```php
for ( $i = 0; $i < $this->iterations; $i++ ) {
    $this->run_math_operations();    // 50,000 iterations
    $this->run_string_operations();  // 10,000 iterations
    $this->run_array_operations();   // 10,000 iterations
}
```

**Recommendation:** This is acceptable for cron execution but could be problematic if triggered via admin AJAX during high traffic.

---

### 3. **Full JSON File Read on Every Sample Add** (Low Impact)
**Location:** `src/Persistence/HealthRepository.php` lines 263-303

Every `add_sample()` call:
1. Reads entire JSON file
2. Decodes it
3. Adds sample
4. Prunes old samples
5. Re-encodes and writes

With 24-hour retention at 15-minute intervals (~96 samples), this is manageable but not optimal.

**Recommendation:** Consider append-only logging with periodic compaction, or use a database table for high-frequency writes.

---

### 4. **Array Operations in Pruning** (Low Impact)
**Location:** `src/Persistence/HealthRepository.php` lines 311-343

```php
$pruned = array_filter($samples, function($sample) use ($cutoff) {...});
$pruned = array_values($pruned);  // Re-index
```

This creates multiple array copies. With ~96 samples, impact is minimal.

---

### 5. **No Object Caching for State Data** (Low Impact)
**Location:** `src/Domain/FsmStateStore.php`

State data is fetched from `wp_options` on every call without using WordPress object cache.

**Recommendation:** Use `wp_cache_get()`/`wp_cache_set()` for frequently accessed state data.

---

### 6. **Inline JavaScript in View Files** (Code Quality)
**Location:** `src/Admin/views/tab-manual-test.php` lines 52-238

Large inline `<script>` block (~180 lines) in PHP view file. This:
- Cannot be cached by browsers
- Mixes concerns
- Makes testing harder

**Recommendation:** Move to external JS file and use `wp_localize_script()` for dynamic data.

---

## 🟢 GOOD PRACTICES OBSERVED

1. **Proper WordPress Coding Standards** - Consistent use of escaping functions (`esc_html()`, `esc_attr()`, `esc_url()`)
2. **Comprehensive Logging** - All operations are logged with context
3. **Circuit Breaker Pattern** - Prevents runaway failures with cooldown
4. **Lock Mechanism** - Prevents concurrent execution with TTL-based expiration
5. **Atomic File Operations** - Prevents data corruption
6. **Nonce Verification** - All AJAX endpoints protected
7. **Capability Checks** - Admin functions require `manage_options`
8. **Singleton Pattern** - Plugin class properly implements singleton
9. **Namespace Usage** - Proper PHP namespacing to avoid conflicts
10. **Dependency Checking** - Graceful degradation when helper plugin missing

---

## 📋 SUMMARY

| Category | Count | Severity |
|----------|-------|----------|
| Security Red Flags | 4 | Low-Medium |
| Performance Issues | 6 | Low-Medium |
| Good Practices | 10+ | N/A |

	### Priority Recommendations:
	
	1. **Add rate limiting to REST endpoint** - Prevents abuse
	2. **Use modern Clipboard API** - Browser compatibility
	3. **Cache state data** - Reduce database queries
	4. **Move inline JS to external file** - Better caching and maintainability
	
	The codebase is generally well-structured with good security practices. The previous audit findings (directory traversal, XSS) have been properly addressed. The remaining issues are relatively minor and don't represent critical vulnerabilities.
	
	Future hardening and performance work for this audit is tracked in the checklist below.
	
	## Task Checklist
	- [x] Harden public cron health REST endpoint with per-IP rate limiting (6 requests / 5 minutes; HTTP 429 on limit exceeded; filterable via `hypercart_server_monitor_cron_health_*`).
	- [ ] Replace deprecated `document.execCommand('copy')` usage with the modern Clipboard API.
	- [ ] Reduce repeated `get_option()` calls in FSM state store by caching within the lock scope.
	- [ ] Move inline Manual Test JavaScript to an external file and wire it via `wp_localize_script()`.
