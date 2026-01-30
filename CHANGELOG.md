# Changelog

All notable changes to Hypercart Server Monitor MKII will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.19] - 2026-01-30

### Fixed
- **State Transition Lock Failure Handling**: Added return value checks for all `transition_to()` calls in the main run flow.
  - Previously, state transitions could silently fail when the state lock couldn't be acquired, causing the run to proceed with stale state.
  - Now logs warnings when state transitions fail due to lock acquisition failures.
  - Improves observability and debugging of state synchronization issues.
  - **Files Modified**: `src/Plugin.php`

- **Probe Failure Lock Failure Handling**: Added fallback error handling when `record_probe_failure()` fails to acquire lock.
  - Previously, if the lock was stuck during a probe failure, the failure/trip record could be lost entirely.
  - Now falls back to `transition_to('error')` when `record_probe_failure()` returns false.
  - Ensures circuit breaker failures are always recorded even if lock acquisition fails.
  - **Files Modified**: `src/Plugin.php`

## [0.4.18] - 2026-01-30

### Performance
- **AJAX Timeout Handling**: Added explicit 10-second timeout to cron health AJAX calls and 15-second timeout to breaker self-test AJAX calls.
  - Timeout-specific error messages now displayed to users when requests exceed time limits.
  - Improves robustness and user feedback per AGNETS.md guidelines.
  - **Files Modified**: `assets/admin.js`

- **Optimized FSM State Reads**: Refactored `run_breaker_self_test()` to reduce redundant database reads.
  - Reduced redundant `get_option()` calls from 7 to 5 per self-test execution.
  - Re-reads state from database after each state-changing operation to ensure correctness.
  - Improves performance while maintaining state integrity and aligns with AGNETS.md "minimize DB calls" guidance.
  - **Files Modified**: `src/Domain/FsmStateStore.php`

### Changed
- **Modern Clipboard API**: Replaced deprecated `document.execCommand('copy')` with async `navigator.clipboard.writeText()`.
  - Graceful fallback to `execCommand` for older browsers that don't support the Clipboard API.
  - Eliminates use of deprecated DOM manipulation for clipboard operations.
  - **Files Modified**: `assets/admin.js`

## [0.4.17] - 2026-01-30

### Added
- **Email Notifications Toggle**: Added checkbox switch to enable/disable automatic email notifications.
  - Modern toggle switch in Email tab with visual on/off state (enabled/disabled).
  - Setting saved via AJAX with nonce verification for security.
  - Default state: Enabled (maintains backward compatibility).
  - Real-time feedback with success/error messages displayed below toggle.
  - Monitoring flow checks setting before sending emails (respects user preference).
  - Option stored in WordPress options table: `hypercart_server_monitor_email_notifications_enabled`.
  - **Files Modified**: `src/Plugin.php`, `src/Admin/views/tab-email.php`, `src/Admin/AdminController.php`, `assets/admin.js`, `hypercart-server-monitor.php`

### Changed
- **Email Tab Description**: Updated from "Email notifications are sent automatically every 15 minutes after each benchmark run" to "Configure automatic email notifications sent after each benchmark run."
- **Monitoring Flow**: `run_monitoring()` method now checks email notifications setting before sending emails (line 348 in Plugin.php).

### Technical Details
- **AJAX Handler**: New `ajax_toggle_email_notifications()` method in AdminController with proper nonce verification and capability checks.
- **JavaScript**: Toggle switch handler in `assets/admin.js` with visual feedback, error handling, and automatic state reversion on failure.
- **CSS**: Modern toggle switch styling with smooth transitions, disabled states, and WordPress admin color scheme integration.
- **Security**: Nonce verification (`hsm_toggle_email_notifications`), capability check (`manage_options`), input sanitization (strict '1'/'0' validation).
- **Logging**: All toggle changes logged with user information for audit trail.
- **Default Behavior**: If option doesn't exist, defaults to '1' (enabled) to maintain backward compatibility with existing installations.

## [0.4.16] - 2026-01-30

### Added
- **Pagination Controls for Frontend Dashboard**: Implemented performant, secure pagination for the Recent Samples table.
  - URL-based pagination using `hsm_page` query parameter for better SEO and browser history support.
  - Shows 10 samples per page with intelligent page number display (ellipsis for large page counts).
  - Displays "Showing X-Y of Z samples" information for user clarity.
  - First/Previous/Next/Last navigation buttons with proper disabled states.
  - Smart page number display: shows all pages if 7 or fewer, otherwise shows first, last, current, and 2 pages on each side of current.
  - Input sanitization: page number is validated and clamped to valid range (1 to total_pages).
  - Modern, accessible design matching the existing slate color palette with blue accent for current page.
  - Fully responsive with mobile-optimized layout and touch-friendly button sizes.
  - Screen reader support with ARIA labels (`aria-current`, `aria-disabled`) and semantic HTML.
  - Smooth hover effects and active states for better UX.
  - **Files Modified**: `src/Frontend/views/shortcode-dashboard.php`, `assets/frontend.css`

### Changed
- **Frontend Dashboard Description**: Updated Recent Samples card description to show current page info (e.g., "Page 1 of 5 (48 total samples)").
- **Table ID**: Added `id="hsm-samples-table"` to the samples table for potential future JavaScript enhancements.

### Technical Details
- **Performance**: No database queries - pagination operates on in-memory array from JSON file (24h of samples, typically 96 entries).
- **Security**: Input sanitization using PHP's `(int)` casting and `max()`/`min()` clamping to prevent invalid page numbers.
- **Accessibility**: Full ARIA support, screen reader text for navigation buttons, semantic navigation structure.
- **SEO**: URL-based pagination allows search engines to discover and index historical data pages.
- **Browser Compatibility**: Uses standard HTML/CSS, no JavaScript required for core functionality.
- **Design System**: Matches existing Tailwind-inspired slate color palette (#f8fafc, #e2e8f0, #cbd5e1, #94a3b8, #64748b, #475569, #1e293b) with blue accent (#3b82f6) for active page.

## [0.4.15] - 2026-01-28

### Added
- **Cron Health Column in Recent Samples**: Added a new "Cron Health" column after Benchmark 3 in both the frontend and admin Recent Samples tables.
  - Each sample now stores a cron health snapshot at run time for historical display.
  - Status shows `HEALTHY`, `UNHEALTHY`, or `N/A` with color-coded styling.
  - **Files Modified**: `src/Plugin.php`, `src/Frontend/views/shortcode-dashboard.php`, `src/Admin/views/tab-dashboard.php`, `assets/frontend.css`, `assets/admin.css`

### Fixed
- **Duplicate Tooltip on Timestamps**: Removed the native HTML `title` tooltip so only the custom CSS tooltip appears.
  - **Files Modified**: `src/Frontend/views/shortcode-dashboard.php`
- **Noindex Notice Font Mismatch**: Forced the "Search Engine Indexing Disabled" notice to use the plugin’s Inter font to match the rest of the dashboard.
  - **Files Modified**: `assets/frontend.css`

## [0.4.14] - 2026-01-27

### Security

- **Cron Health Endpoint Rate Limiting**: Added per-IP rate limiting to the public cron health REST API endpoint.
	- Endpoint: `/wp-json/cron-health/v1/status` remains read-only but now enforces rate limits.
	- Default limit: 6 requests per 5 minutes per IP (filter: `hypercart_server_monitor_cron_health_rate_limit`).
	- Window: 300 seconds (5 minutes) per IP (filter: `hypercart_server_monitor_cron_health_window_seconds`).
	- On limit exceeded, returns HTTP 429 with JSON body: `{"status":"rate_limited","message":"Rate limit exceeded, try again later."}`.
	- Uses WordPress transients keyed by IP hash to avoid custom tables and respect caching layers.
	- **Files Modified**: `src/Plugin.php`, `hypercart-server-monitor.php`

## [0.4.13] - 2026-01-25

### Added
- **Cron Health Check REST API Endpoint**: New REST API endpoint for external monitoring tools to verify cron execution health.
  - Endpoint: `/wp-json/cron-health/v1/status` returns JSON with `status` (healthy/unhealthy) and `last_run` timestamp.
  - Tracks last cron run timestamp in WordPress options (`hypercart_server_monitor_last_cron_run`).
  - Sets transient (`hypercart_server_monitor_cron_health_check`) with 1-hour expiration on each scheduled run.
  - Health status determined by presence of last_run, next_run, and valid transient.
  - Public endpoint (no authentication required) for easy integration with external monitoring services.
  - **Files Modified**: `src/Plugin.php` (added constants, REST endpoint registration, and health check logic)

- **Cron Health Check UI in Debug Tab**: New admin interface section for monitoring cron health.
  - Displays REST endpoint URL with "Copy URL" button for easy sharing with monitoring tools.
  - Shows last cron run timestamp with human-readable "time ago" format.
  - Auto-loads health status on page load using AJAX call to REST endpoint.
  - "Test Health Endpoint" button to manually refresh health status.
  - Visual status indicators (✓ Healthy / ✗ Unhealthy) with color coding.
  - **Explanation Section**: Added styled information box explaining how the health check works:
    - Lists the three conditions required for "healthy" status (last_run exists, next_run scheduled, transient valid).
    - Documents automatic update frequency (every 15 minutes).
    - Shows example response format for integration reference.
    - "Copy Explanation" button to copy the full explanation text to clipboard for sharing with team members or documentation.
  - **Files Modified**: `src/Admin/views/tab-debug.php`, `assets/admin.js`, `src/Admin/AdminController.php`

### Technical Details
- REST endpoint uses WordPress REST API infrastructure with `register_rest_route()`.
- Health check logic: `(last_run && next_run && transient_exists) ? 'healthy' : 'unhealthy'`.
- Last run timestamp updated only for scheduled runs (not manual tests) via `use_scheduled_state` option.
- JavaScript uses REST URL from localized script data (`hsmAdmin.restUrl`).
- Copy-to-clipboard functionality uses temporary input element and `document.execCommand('copy')`.

## [0.4.12] - 2026-01-25

### Added
- **Modern Frontend Design**: Complete redesign of the frontend shortcode dashboard with a modern, Tailwind CSS-inspired aesthetic.
  - New `assets/frontend.css` file (570 lines) with modern styling including Inter font family, card-based layout, and slate color palette.
  - Restructured `src/Frontend/views/shortcode-dashboard.php` with semantic HTML and modern components.
  - New header section with "Server Health 2026" title and read-only badge indicator.
  - Modern notice/alert boxes with SVG icons for warnings and information.
  - 2-column grid layout separating "Current Performance Score" and "Benchmark Metrics" into distinct cards.
  - Color-coded score badges (Excellent/Good/Warning/Critical) with corresponding visual styling.
  - Metrics displayed as clean list items instead of table format for better readability.
  - Completely redesigned Recent Samples table with modern styling, hover effects, and improved typography.
  - Responsive design with mobile-first approach and breakpoints at 768px.
  - **Files Modified**: `src/Plugin.php` (now enqueues `frontend.css` instead of `admin.css` for shortcode)
  - **Files Created**: `assets/frontend.css`

- **UTC Timestamp Display with Local Time Tooltips**: All timestamps now display in UTC with hover tooltips showing local time.
  - All timestamps throughout the frontend dashboard now display in UTC (Coordinated Universal Time) for consistency and scraper-friendly data.
  - Hovering over any timestamp reveals a modern CSS tooltip showing the equivalent local time in the WordPress configured timezone.
  - Table header explicitly labeled "Timestamp (UTC)" for clarity.
  - Data attributes added for scraping tools: `data-hsm-utc`, `data-hsm-local`, `data-hsm-timezone`, and `data-hsm-table="UTC"`.
  - Timezone notice updated to explain: "All timestamps are displayed in UTC. Hover over any timestamp to see your local time."
  - Native HTML `title` attribute provides fallback tooltip for accessibility and mobile devices.
  - CSS tooltips automatically disabled on touch devices (mobile/tablet) to prevent UX issues.
  - Tooltip styling matches modern design with dark background, rounded corners, and smooth animations.
  - **Implementation**: Pure CSS solution (no JavaScript required) using `::before` and `::after` pseudo-elements.
  - **Files Modified**: `src/Frontend/views/shortcode-dashboard.php`, `assets/frontend.css`

## [0.4.11] - 2026-01-20

### Added
- **Timezone Notice**: Both Backend Plugin Dashboard and Frontend Shortcode Dashboard now display a notice showing the current timezone being used for time displays.
  - Notice format: "Note: Showing Local Time ({timezone}). You may need to convert to UTC elsewhere."
  - Uses WordPress `wp_timezone_string()` to display the configured timezone name.
  - Helps users understand that displayed times are in their local timezone and may need conversion to UTC for other purposes.
  - **Files Modified**: `src/Admin/views/tab-dashboard.php`, `src/Frontend/views/shortcode-dashboard.php`

## [0.4.10] - 2026-01-18

### Updated
- Admin UI labels clarify per-run iterations and rename benchmark columns to Run 1-3; added total samples row in dashboard metrics.
- Admin page title now includes plugin version and menu label reflects full product name.
- Debug self-test note now links to the Hypercart Helper SECURITY-README viewer (clickable).
- Frontend shortcode metrics table now includes scraper-friendly data attributes and structured value/unit spans.
- Changelog wording aligned to implementation (noindex via `wp_head`, legacy collectors marked as deprecated but present).

## [0.4.7] - 2026-01-18

### Added
- **SEO Control for Shortcode**: The `[hypercart_server_monitor_dashboard]` shortcode now prevents the page from being indexed by search engines by default.
- A `noindex` meta tag is added to the page `<head>` via a `wp_head` action hooked during `wp`.
  - This can be disabled by using the attribute `[hypercart_server_monitor_dashboard noindex="false"]`.
  - A notice is displayed on the frontend dashboard to inform the user that indexing is disabled.
  - **Files Modified**: `src/Plugin.php`, `src/Frontend/views/shortcode-dashboard.php`

## [0.4.2] - 2026-01-17

### Security
- Added capability checks before rendering admin notices. `wp-server-performance-monitor.php`
- Added capability check before enqueuing admin assets. `src/Admin/AdminController.php`

- Added Self Tests for Circuit Breaker

## [0.4.3] - 2026-01-18

### Reliability
- Centralized breaker gating in the FSM store, including cooldown handling and half-open probe runs. `src/Domain/FsmStateStore.php`, `src/Plugin.php`
- Manual tests now follow the same breaker rules as scheduled runs. `src/Plugin.php`, `src/Admin/AdminController.php`

## [0.4.4] - 2026-01-18

### Reliability
- Added a benchmark timeout with a filterable max runtime. `src/Metrics/BenchmarkCollector.php`

## [0.4.5] - 2026-01-18

### Diagnostics
- Added a Debug tab breaker self-test button with results output. `src/Admin/AdminController.php`, `src/Admin/views/tab-debug.php`, `assets/admin.js`

## [0.4.9] - 2026-01-18

### Fixed
- **Fixed**: False positive dependency check for Hypercart Helper
- **Location**: `wp-server-performance-monitor.php` lines 76-91
- **Issue**: Plugin showed "missing dependency" error even when Helper v1.1.0 was active and working
- **Root Cause**: Dependency check was too strict, checking for classes without verifying they were actually missing
- **Fix**: Improved dependency detection logic
  - Core check: Only requires `Hypercart_Time` and `Hypercart_Logger` (v1.0.0+) - BLOCKING
  - Feature check: Only shows version notice if BOTH version < 1.1.2 AND classes are actually missing - NON-BLOCKING
  - Smart detection: Checks actual class existence, not just version number
- **Result**: Plugin loads successfully if core classes exist, regardless of version number
- **Behavior**:
  - Helper missing: Plugin blocks with error notice
  - Helper v1.0.0+ with core classes: Plugin loads successfully
  - Helper < v1.1.2 AND missing Admin Tabs/Charts: Shows upgrade notice (non-blocking)
  - Helper v1.1.2+ OR has Admin Tabs/Charts: No notices

## [0.4.8] - 2026-01-18

### Improved
- **Improved**: Hide noindex notice from non-admin users
- **Location**: `src/Frontend/views/shortcode-dashboard.php`
- **Change**: Added `current_user_can( 'manage_options' )` check to noindex warning notice
- **Reason**: Non-admin users don't need to see implementation details
- **Result**: Notice only shows to administrators who can actually modify the shortcode
- **Security**: Reduces information disclosure to public users

### Testing
- **Tested**: `noindex="false"` - Verified meta tag is NOT added when disabled
- **Tested**: `noindex="true"` (default) - Verified meta tag IS added
- **Tested**: Notice visibility - Confirmed hidden from anonymous users (via curl)
- **Tested**: Meta tag placement - Confirmed appears in `<head>` section

## [0.4.7] - 2026-01-18

### Fixed
- **Fixed**: Noindex meta tag timing issue in frontend shortcode
- **Location**: `src/Plugin.php`
- **Issue**: Noindex meta tag was not being added to `<head>` because filter was added too late
- **Root Cause**: Shortcode rendering happens after `wp_head` action, so adding filter during shortcode execution was too late
- **Fix**: Added `detect_shortcode_and_add_noindex()` method that runs during `wp` action (before `wp_head`)
- **Implementation**: Pre-parses post content to detect shortcode and extract `noindex` attribute
- **Result**: `<meta name="robots" content="noindex, nofollow" />` now correctly appears in `<head>` when `noindex="true"`
- **Tested**: Verified via curl on https://neochrome-timesheets.local/health-2026/

## [0.4.6] - 2026-01-18

### Added
- Read-only frontend dashboard shortcode (`[hypercart_server_monitor_dashboard]`). `src/Plugin.php`, `src/Frontend/views/shortcode-dashboard.php`

### Security
- **Fixed**: DOM-based XSS vulnerability in Manual Test tab
- **Location**: `src/Admin/views/tab-manual-test.php`
- **Vulnerability**: Unescaped server response data inserted into HTML via string concatenation
- **Attack Vector**: Malicious server response could inject JavaScript in admin context
- **Fix**: Added `escapeHtml()` JavaScript function to sanitize all dynamic content before DOM insertion
- **Affected Fields**: Warning messages, score labels, timestamps
- **Severity**: Medium (requires admin access + compromised server response)
- **Impact**: Prevents HTML/JavaScript injection in admin dashboard

### Security Fixes - Audit Response

**Critical security and hardening improvements based on code audit.**

#### Log File Allowlist (CRITICAL)
- **Fixed**: Directory traversal vulnerability in log viewer
- **Location**: `AdminController::render_tab_logs()`
- **Change**: Added strict allowlist check against `Hypercart_Logger::get_log_files()`
- **Security**: Rejects any log file not in the allowlist before calling `read_log()`
- **Logging**: Logs rejected attempts with username for security monitoring
- **Impact**: Prevents arbitrary file read via `$_GET['log_file']` parameter

#### Subject Line Float Handling
- **Fixed**: Float truncation in email subject line
- **Location**: `EmailService::build_subject()`
- **Change**: Changed `%d` to `%.0f` for score formatting
- **Change**: Changed `%.2f` to `%.1f` for benchmark time (consistency)
- **Impact**: Correctly handles float scores without truncation

#### .htaccess Hardening
- **Enhanced**: Directory protection file creation
- **Location**: `HealthRepository::ensure_directory()`
- **Added**: Apache 2.4+ compatible directives (`Require all denied`)
- **Added**: Fallback for Apache 2.2 (`Deny from all`)
- **Added**: `index.html` file for directory listing protection (works on all servers)
- **Added**: Error handling and logging for failed file writes
- **Impact**: Better defense-in-depth for data directory protection

#### Max File Size Check
- **Added**: 1MB file size limit in JSON reader
- **Location**: `HealthRepository::read()`
- **Check**: Validates file size before reading content
- **Limit**: 1MB (expected size is ~10-20KB with 24h pruning)
- **Action**: Archives oversized files and resets to empty structure
- **Impact**: Prevents memory exhaustion from unexpected file growth

### Files Modified
- `src/Admin/AdminController.php` - Log file allowlist
- `src/Services/EmailService.php` - Subject line float fix
- `src/Persistence/HealthRepository.php` - .htaccess hardening + file size check

### Security Posture
- ✅ **Critical**: Directory traversal vulnerability fixed
- ✅ **Hardening**: Multiple defense-in-depth improvements
- ✅ **Logging**: Security events now logged for monitoring
- ✅ **Robustness**: Better error handling for filesystem operations

---

## [0.4.0] - 2026-01-10

### Added - Phase 5: Email Notifications

**Email notifications are now sent automatically after each benchmark run!**

#### EmailService
- **New Service**: `src/Services/EmailService.php`
- **Dynamic Subject Line**: `[Server Monitor] Score: 100 (Excellent) | Benchmark: 36.5ms`
  - Score and benchmark time visible at a glance in inbox
  - Color-coded score labels (Excellent/Good/Warning/Critical)
- **HTML Email Body**:
  - Site name and URL
  - Large score display with color coding
  - Benchmark details (avg/min/max/iterations)
  - UTC and local timestamps
  - Link to admin dashboard
  - Plugin version in footer
- **Recipient**: Uses `get_option('admin_email')` (configurable in future)
- **Logging**: All email sends logged via `Hypercart_Logger`

#### FSM Integration
- **New State**: `emailing` added to FSM workflow
- **Flow**: `writing` → `emailing` → `completed`
- **Error Handling**: Email failures logged but don't stop workflow
- **Location**: `Plugin::run_monitoring()` lines 177-188

#### Manual Test Email
- **New Tab**: "Email" tab in admin UI
- **Button**: "Send Test Email" sends most recent saved benchmark
- **No New Benchmark**: Uses existing data from JSON file
- **AJAX Handler**: `ajax_send_test_email()` in `AdminController`
- **Validation**: Checks for existing data before sending
- **Feedback**: Success/error messages with recipient confirmation

#### Files Added
- `src/Services/EmailService.php` - Email formatting and sending
- `src/Admin/views/tab-email.php` - Email tab UI with test button

#### Files Modified
- `src/Plugin.php` - Added email sending to FSM workflow
- `src/Admin/AdminController.php` - Added Email tab and AJAX handler
- `README.md` - Documented email requirements

#### Manual Cron Scheduling
- **New Button**: "Schedule Cron Now" in Debug tab
- **Auto-hide**: Only shows if cron is not scheduled
- **AJAX Handler**: `ajax_schedule_cron()` in `AdminController`
- **Auto-reload**: Page reloads after successful scheduling
- **Use Case**: Fix cron scheduling issues without deactivating plugin

### Benefits
- ✅ Instant notification of performance issues
- ✅ Score visible in subject line (no need to open email)
- ✅ Test email functionality for debugging SMTP
- ✅ FSM-first approach (email after JSON write)
- ✅ Non-blocking (email failures don't stop monitoring)
- ✅ Manual cron scheduling button for troubleshooting

---

## [0.3.0] - 2026-01-10

### Changed - Simplified to Single Synthetic Benchmark

**BREAKING CHANGE**: Replaced system-specific metrics (CPU, Memory, Disk) with a single synthetic PHP performance benchmark.

### Fixed
- **Score Storage Bug**: Fixed `Plugin::run_monitoring()` to save detailed score array (with `combined`, `label`, `benchmark_ms`) instead of just integer
- **UI Clarity**: Added "↓ lower is better" indicator next to benchmark time in dashboard and manual test views
- **Logging**: Enhanced logging to show score, label, and benchmark time separately for better debugging

#### Rationale
- System metrics (especially Memory) are not supported on all environments (e.g., macOS Local)
- Multiple metric types multiplied complexity unnecessarily
- Single benchmark provides universal compatibility and simpler framework for testing scheduling/email features

#### New Benchmark System
- **BenchmarkCollector**: Runs 3 iterations of compute-intensive PHP tasks
  - Mathematical operations (50,000 iterations of sqrt/sin/cos)
  - String operations (10,000 MD5 hashes with substring management)
  - Array operations (10,000 elements with sort/filter/map/reduce)
- **Scoring**: Based on average execution time (lower = better)
  - ≤100ms → Score 100
  - 100-200ms → Linear 100-50
  - 200-400ms → Linear 50-20
  - >400ms → Score 0-10
- **Metrics Returned**:
  - `avg_time_ms`: Average execution time
  - `min_time_ms`: Fastest run
  - `max_time_ms`: Slowest run
  - `iterations`: Number of runs (3)
  - `all_times_ms`: Array of all run times

#### Updated Components
- **ScoringService**: Simplified to single `score_benchmark()` method
- **Plugin::collect_metrics()**: Now uses only `BenchmarkCollector`
- **AdminController**: Updated for single metric
- **Dashboard Tab**: Shows benchmark details (avg/min/max/iterations)
- **Manual Test Tab**: Shows benchmark execution times instead of system metrics
- **Deprecated**: `CpuLoadCollector.php`, `MemoryCollector.php`, `DiskCollector.php` are no longer used but still present in `src/Metrics`.

#### Benefits
- ✅ Works on ALL environments (Local, staging, production, any OS)
- ✅ Consistent, reproducible results
- ✅ Simpler codebase (1 collector vs 3)
- ✅ Focus on framework reliability (scheduling, FSM, email)
- ✅ No platform-specific edge cases

---

## [0.2.0] - 2026-01-10

### Fixed
- **Manual Test Runner**: Now properly handles unsupported metrics and displays results reliably
  - **CRITICAL FIX**: `ScoringService::calculate_score()` now supports detailed output format
    - Added `$detailed` parameter to return array with `combined`, `cpu`, `memory`, `disk`, `label`
    - Handles both old flat format and new nested collector format
    - AdminController now requests detailed format for proper display
  - Added "Status" column to metrics table showing "Supported" or "Not Supported"
  - Displays warnings for unsupported metrics at top of results
  - Shows logging status confirmation ("Test logged to Hypercart logs")
  - Gracefully handles missing metric values with fallback to 0 or "—"
  - Logs manual test start/completion to Hypercart logs for debugging
  - Error messages now show logging status
  - **Enhanced Debugging**:
    - Added console logging for all AJAX responses
    - Validates score and metrics data before rendering
    - Try-catch block around HTML building to catch JavaScript errors
    - Logs collected metrics at DEBUG level for troubleshooting
    - Validates scoring service response and throws exception if invalid
    - Better error messages with browser console guidance

### Added - Phase 4: Admin UI with Tabbed Navigation

#### Admin Controller (`AdminController`)
- New `AdminController` class using `Hypercart_Admin_Tabs` helper
- Top-level admin menu page with "Server Monitor" label
- Dashicons icon: `dashicons-performance`
- Four-tab interface: Dashboard, Manual Test, Logs, Debug
- AJAX endpoint for manual test runner
- Asset enqueuing for admin CSS/JS and Hypercart Charts

#### Dashboard Tab
- **Current Health Score Display**
  - Large score number (0-100) with color-coded background
  - Score label: Excellent (90+), Good (75-89), Warning (60-74), Critical (<60)
  - Last updated timestamp in site timezone
- **Metric Breakdown Table**
  - CPU Load (raw value + score)
  - Memory Usage (percentage + score)
  - Disk Free (percentage + score)
- **Health Score Chart (24 Hours)**
  - Time-series chart using `Hypercart_Charts`
  - Interactive hover tooltips
  - Automatic timezone conversion
- **Recent Samples Table**
  - Last 10 samples in reverse chronological order
  - Timestamp, score, label, CPU, memory, disk columns

#### Manual Test Tab
- **Run Test Now Button**
  - Executes monitoring without saving to database
  - Real-time AJAX results display
  - Shows score, metrics breakdown, duration
  - Does NOT affect FSM state or trigger emails
- **Test Results Display**
  - Color-coded score display
  - Metrics table with raw values and scores
  - Completion timestamp and duration
- **Info Panel**
  - Explains manual test behavior
  - Lists what manual tests do NOT do

#### Logs Tab
- **Log File Selector**
  - Dropdown with all available log files
  - Shows filename and file size
  - Auto-submit on selection
- **Log Level Filtering**
  - Filter buttons: All, Debug, Info, Warning, Error
  - Real-time client-side filtering
  - Active button highlighting
- **Log Content Display**
  - Reverse chronological order (newest first)
  - Color-coded by level (ERROR=red, WARNING=yellow, INFO=cyan, DEBUG=blue)
  - Dark theme code editor styling
  - Only shows logs from this plugin
  - Scrollable container (max 600px height)
- **Info Panel**
  - Log file location
  - Naming convention
  - Display order explanation

#### Debug Tab
- **FSM State Panel**
  - Current state
  - Last updated timestamp
  - Failure count (color-coded: red if ≥3)
  - Last error message
  - Last run timestamp
  - Last duration in milliseconds
- **Cron Status Panel**
  - Scheduled status (Yes/No)
  - Next run timestamp (site timezone)
  - Hook name
  - Interval name
- **Lock Status Panel**
  - Locked status (Yes/No)
  - Acquired at timestamp
  - Lock age in seconds
- **File Status Panel**
  - JSON file path
  - Exists status
  - Writable status
  - File size in KB
  - Sample count
  - Oldest/newest sample timestamps

#### Assets
- **admin.css**
  - Card-based layout
  - Color-coded score displays
  - Responsive metrics tables
  - Dark theme log viewer
  - Status indicators (ok/warning/error)
  - Button styling
- **admin.js**
  - Placeholder for future enhancements
  - jQuery-based

### Changed
- **Minimum Hypercart Helper Version**: Now requires v1.1.2+ (for `Hypercart_Admin_Tabs` and `Hypercart_Charts`)
- **Plugin Version**: Bumped to 0.2.0
- **Settings Link**: Changed from "Settings" to "Dashboard" and updated URL to new admin page
- **Admin Menu**: Moved from Tools submenu to top-level menu with custom icon

### Technical Details
- **New Files**:
  - `src/Admin/AdminController.php`
  - `src/Admin/views/tab-dashboard.php`
  - `src/Admin/views/tab-manual-test.php`
  - `src/Admin/views/tab-logs.php`
  - `src/Admin/views/tab-debug.php`
  - `assets/admin.css`
  - `assets/admin.js`
- **Modified Files**:
  - `src/Plugin.php` - Added AdminController initialization
  - `wp-server-performance-monitor.php` - Updated version and dependency checks
- **AJAX Actions**:
  - `hsm_run_manual_test` - Manual test runner endpoint
- **Capabilities**: All admin pages require `manage_options`

### Dependencies
- WordPress 5.8+
- PHP 7.4+
- **Hypercart Helper 1.1.2+** (updated from 1.0.0)

---

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
- Email notifications with score in subject (Phase 5)
- Self-test diagnostics (Phase 6)
- Export/import settings
- Dashboard widget (optional)
- Admin bar indicator (optional)

---

**Version:** 0.4.0
**Date:** 2026-01-10
**Status:** Phase 5 complete - Email notifications with dynamic subject line and manual test button


### Security
- Added capability checks before rendering admin notices. `wp-server-performance-monitor.php`
- Added capability check before enqueuing admin assets. `src/Admin/AdminController.php`

- Added Self Tests for Circuit Breaker

## [0.4.3] - 2026-01-18

### Reliability
- Centralized breaker gating in the FSM store, including cooldown handling and half-open probe runs. `src/Domain/FsmStateStore.php`, `src/Plugin.php`
- Manual tests now follow the same breaker rules as scheduled runs. `src/Plugin.php`, `src/Admin/AdminController.php`

## [0.4.4] - 2026-01-18

### Reliability
- Added a benchmark timeout with a filterable max runtime. `src/Metrics/BenchmarkCollector.php`

## [0.4.5] - 2026-01-18

### Diagnostics
- Added a Debug tab breaker self-test button with results output. `src/Admin/AdminController.php`, `src/Admin/views/tab-debug.php`, `assets/admin.js`

## [0.4.6] - 2026-01-18

### Added
- Read-only frontend dashboard shortcode (`[hypercart_server_monitor_dashboard]`). `src/Plugin.php`, `src/Frontend/views/shortcode-dashboard.php`

### Security
- **Fixed**: DOM-based XSS vulnerability in Manual Test tab
- **Location**: `src/Admin/views/tab-manual-test.php`
- **Vulnerability**: Unescaped server response data inserted into HTML via string concatenation
- **Attack Vector**: Malicious server response could inject JavaScript in admin context
- **Fix**: Added `escapeHtml()` JavaScript function to sanitize all dynamic content before DOM insertion
- **Affected Fields**: Warning messages, score labels, timestamps
- **Severity**: Medium (requires admin access + compromised server response)
- **Impact**: Prevents HTML/JavaScript injection in admin dashboard

### Security Fixes - Audit Response

**Critical security and hardening improvements based on code audit.**

#### Log File Allowlist (CRITICAL)
- **Fixed**: Directory traversal vulnerability in log viewer
- **Location**: `AdminController::render_tab_logs()`
- **Change**: Added strict allowlist check against `Hypercart_Logger::get_log_files()`
- **Security**: Rejects any log file not in the allowlist before calling `read_log()`
- **Logging**: Logs rejected attempts with username for security monitoring
- **Impact**: Prevents arbitrary file read via `$_GET['log_file']` parameter

#### Subject Line Float Handling
- **Fixed**: Float truncation in email subject line
- **Location**: `EmailService::build_subject()`
- **Change**: Changed `%d` to `%.0f` for score formatting
- **Change**: Changed `%.2f` to `%.1f` for benchmark time (consistency)
- **Impact**: Correctly handles float scores without truncation

#### .htaccess Hardening
- **Enhanced**: Directory protection file creation
- **Location**: `HealthRepository::ensure_directory()`
- **Added**: Apache 2.4+ compatible directives (`Require all denied`)
- **Added**: Fallback for Apache 2.2 (`Deny from all`)
- **Added**: `index.html` file for directory listing protection (works on all servers)
- **Added**: Error handling and logging for failed file writes
- **Impact**: Better defense-in-depth for data directory protection

#### Max File Size Check
- **Added**: 1MB file size limit in JSON reader
- **Location**: `HealthRepository::read()`
- **Check**: Validates file size before reading content
- **Limit**: 1MB (expected size is ~10-20KB with 24h pruning)
- **Action**: Archives oversized files and resets to empty structure
- **Impact**: Prevents memory exhaustion from unexpected file growth

### Files Modified
- `src/Admin/AdminController.php` - Log file allowlist
- `src/Services/EmailService.php` - Subject line float fix
- `src/Persistence/HealthRepository.php` - .htaccess hardening + file size check

### Security Posture
- ✅ **Critical**: Directory traversal vulnerability fixed
- ✅ **Hardening**: Multiple defense-in-depth improvements
- ✅ **Logging**: Security events now logged for monitoring
- ✅ **Robustness**: Better error handling for filesystem operations

---

## [0.4.0] - 2026-01-10

### Added - Phase 5: Email Notifications

**Email notifications are now sent automatically after each benchmark run!**

#### EmailService
- **New Service**: `src/Services/EmailService.php`
- **Dynamic Subject Line**: `[Server Monitor] Score: 100 (Excellent) | Benchmark: 36.5ms`
  - Score and benchmark time visible at a glance in inbox
  - Color-coded score labels (Excellent/Good/Warning/Critical)
- **HTML Email Body**:
  - Site name and URL
  - Large score display with color coding
  - Benchmark details (avg/min/max/iterations)
  - UTC and local timestamps
  - Link to admin dashboard
  - Plugin version in footer
- **Recipient**: Uses `get_option('admin_email')` (configurable in future)
- **Logging**: All email sends logged via `Hypercart_Logger`

#### FSM Integration
- **New State**: `emailing` added to FSM workflow
- **Flow**: `writing` → `emailing` → `completed`
- **Error Handling**: Email failures logged but don't stop workflow
- **Location**: `Plugin::run_monitoring()` lines 177-188

#### Manual Test Email
- **New Tab**: "Email" tab in admin UI
- **Button**: "Send Test Email" sends most recent saved benchmark
- **No New Benchmark**: Uses existing data from JSON file
- **AJAX Handler**: `ajax_send_test_email()` in `AdminController`
- **Validation**: Checks for existing data before sending
- **Feedback**: Success/error messages with recipient confirmation

#### Files Added
- `src/Services/EmailService.php` - Email formatting and sending
- `src/Admin/views/tab-email.php` - Email tab UI with test button

#### Files Modified
- `src/Plugin.php` - Added email sending to FSM workflow
- `src/Admin/AdminController.php` - Added Email tab and AJAX handler
- `README.md` - Documented email requirements

#### Manual Cron Scheduling
- **New Button**: "Schedule Cron Now" in Debug tab
- **Auto-hide**: Only shows if cron is not scheduled
- **AJAX Handler**: `ajax_schedule_cron()` in `AdminController`
- **Auto-reload**: Page reloads after successful scheduling
- **Use Case**: Fix cron scheduling issues without deactivating plugin

### Benefits
- ✅ Instant notification of performance issues
- ✅ Score visible in subject line (no need to open email)
- ✅ Test email functionality for debugging SMTP
- ✅ FSM-first approach (email after JSON write)
- ✅ Non-blocking (email failures don't stop monitoring)
- ✅ Manual cron scheduling button for troubleshooting

---

## [0.3.0] - 2026-01-10

### Changed - Simplified to Single Synthetic Benchmark

**BREAKING CHANGE**: Replaced system-specific metrics (CPU, Memory, Disk) with a single synthetic PHP performance benchmark.

### Fixed
- **Score Storage Bug**: Fixed `Plugin::run_monitoring()` to save detailed score array (with `combined`, `label`, `benchmark_ms`) instead of just integer
- **UI Clarity**: Added "↓ lower is better" indicator next to benchmark time in dashboard and manual test views
- **Logging**: Enhanced logging to show score, label, and benchmark time separately for better debugging

#### Rationale
- System metrics (especially Memory) are not supported on all environments (e.g., macOS Local)
- Multiple metric types multiplied complexity unnecessarily
- Single benchmark provides universal compatibility and simpler framework for testing scheduling/email features

#### New Benchmark System
- **BenchmarkCollector**: Runs 3 iterations of compute-intensive PHP tasks
  - Mathematical operations (50,000 iterations of sqrt/sin/cos)
  - String operations (10,000 MD5 hashes with substring management)
  - Array operations (10,000 elements with sort/filter/map/reduce)
- **Scoring**: Based on average execution time (lower = better)
  - ≤100ms → Score 100
  - 100-200ms → Linear 100-50
  - 200-400ms → Linear 50-20
  - >400ms → Score 0-10
- **Metrics Returned**:
  - `avg_time_ms`: Average execution time
  - `min_time_ms`: Fastest run
  - `max_time_ms`: Slowest run
  - `iterations`: Number of runs (3)
  - `all_times_ms`: Array of all run times

#### Updated Components
- **ScoringService**: Simplified to single `score_benchmark()` method
- **Plugin::collect_metrics()**: Now uses only `BenchmarkCollector`
- **AdminController**: Updated for single metric
- **Dashboard Tab**: Shows benchmark details (avg/min/max/iterations)
- **Manual Test Tab**: Shows benchmark execution times instead of system metrics
- **Deprecated**: `CpuLoadCollector.php`, `MemoryCollector.php`, `DiskCollector.php` are no longer used but still present in `src/Metrics`.

#### Benefits
- ✅ Works on ALL environments (Local, staging, production, any OS)
- ✅ Consistent, reproducible results
- ✅ Simpler codebase (1 collector vs 3)
- ✅ Focus on framework reliability (scheduling, FSM, email)
- ✅ No platform-specific edge cases

---

## [0.2.0] - 2026-01-10

### Fixed
- **Manual Test Runner**: Now properly handles unsupported metrics and displays results reliably
  - **CRITICAL FIX**: `ScoringService::calculate_score()` now supports detailed output format
    - Added `$detailed` parameter to return array with `combined`, `cpu`, `memory`, `disk`, `label`
    - Handles both old flat format and new nested collector format
    - AdminController now requests detailed format for proper display
  - Added "Status" column to metrics table showing "Supported" or "Not Supported"
  - Displays warnings for unsupported metrics at top of results
  - Shows logging status confirmation ("Test logged to Hypercart logs")
  - Gracefully handles missing metric values with fallback to 0 or "—"
  - Logs manual test start/completion to Hypercart logs for debugging
  - Error messages now show logging status
  - **Enhanced Debugging**:
    - Added console logging for all AJAX responses
    - Validates score and metrics data before rendering
    - Try-catch block around HTML building to catch JavaScript errors
    - Logs collected metrics at DEBUG level for troubleshooting
    - Validates scoring service response and throws exception if invalid
    - Better error messages with browser console guidance

### Added - Phase 4: Admin UI with Tabbed Navigation

#### Admin Controller (`AdminController`)
- New `AdminController` class using `Hypercart_Admin_Tabs` helper
- Top-level admin menu page with "Server Monitor" label
- Dashicons icon: `dashicons-performance`
- Four-tab interface: Dashboard, Manual Test, Logs, Debug
- AJAX endpoint for manual test runner
- Asset enqueuing for admin CSS/JS and Hypercart Charts

#### Dashboard Tab
- **Current Health Score Display**
  - Large score number (0-100) with color-coded background
  - Score label: Excellent (90+), Good (75-89), Warning (60-74), Critical (<60)
  - Last updated timestamp in site timezone
- **Metric Breakdown Table**
  - CPU Load (raw value + score)
  - Memory Usage (percentage + score)
  - Disk Free (percentage + score)
- **Health Score Chart (24 Hours)**
  - Time-series chart using `Hypercart_Charts`
  - Interactive hover tooltips
  - Automatic timezone conversion
- **Recent Samples Table**
  - Last 10 samples in reverse chronological order
  - Timestamp, score, label, CPU, memory, disk columns

#### Manual Test Tab
- **Run Test Now Button**
  - Executes monitoring without saving to database
  - Real-time AJAX results display
  - Shows score, metrics breakdown, duration
  - Does NOT affect FSM state or trigger emails
- **Test Results Display**
  - Color-coded score display
  - Metrics table with raw values and scores
  - Completion timestamp and duration
- **Info Panel**
  - Explains manual test behavior
  - Lists what manual tests do NOT do

#### Logs Tab
- **Log File Selector**
  - Dropdown with all available log files
  - Shows filename and file size
  - Auto-submit on selection
- **Log Level Filtering**
  - Filter buttons: All, Debug, Info, Warning, Error
  - Real-time client-side filtering
  - Active button highlighting
- **Log Content Display**
  - Reverse chronological order (newest first)
  - Color-coded by level (ERROR=red, WARNING=yellow, INFO=cyan, DEBUG=blue)
  - Dark theme code editor styling
  - Only shows logs from this plugin
  - Scrollable container (max 600px height)
- **Info Panel**
  - Log file location
  - Naming convention
  - Display order explanation

#### Debug Tab
- **FSM State Panel**
  - Current state
  - Last updated timestamp
  - Failure count (color-coded: red if ≥3)
  - Last error message
  - Last run timestamp
  - Last duration in milliseconds
- **Cron Status Panel**
  - Scheduled status (Yes/No)
  - Next run timestamp (site timezone)
  - Hook name
  - Interval name
- **Lock Status Panel**
  - Locked status (Yes/No)
  - Acquired at timestamp
  - Lock age in seconds
- **File Status Panel**
  - JSON file path
  - Exists status
  - Writable status
  - File size in KB
  - Sample count
  - Oldest/newest sample timestamps

#### Assets
- **admin.css**
  - Card-based layout
  - Color-coded score displays
  - Responsive metrics tables
  - Dark theme log viewer
  - Status indicators (ok/warning/error)
  - Button styling
- **admin.js**
  - Placeholder for future enhancements
  - jQuery-based

### Changed
- **Minimum Hypercart Helper Version**: Now requires v1.1.2+ (for `Hypercart_Admin_Tabs` and `Hypercart_Charts`)
- **Plugin Version**: Bumped to 0.2.0
- **Settings Link**: Changed from "Settings" to "Dashboard" and updated URL to new admin page
- **Admin Menu**: Moved from Tools submenu to top-level menu with custom icon

### Technical Details
- **New Files**:
  - `src/Admin/AdminController.php`
  - `src/Admin/views/tab-dashboard.php`
  - `src/Admin/views/tab-manual-test.php`
  - `src/Admin/views/tab-logs.php`
  - `src/Admin/views/tab-debug.php`
  - `assets/admin.css`
  - `assets/admin.js`
- **Modified Files**:
  - `src/Plugin.php` - Added AdminController initialization
  - `wp-server-performance-monitor.php` - Updated version and dependency checks
- **AJAX Actions**:
  - `hsm_run_manual_test` - Manual test runner endpoint
- **Capabilities**: All admin pages require `manage_options`

### Dependencies
- WordPress 5.8+
- PHP 7.4+
- **Hypercart Helper 1.1.2+** (updated from 1.0.0)

---

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
- Email notifications with score in subject (Phase 5)
- Self-test diagnostics (Phase 6)
- Export/import settings
- Dashboard widget (optional)
- Admin bar indicator (optional)

---

**Version:** 0.4.0
**Date:** 2026-01-10
**Status:** Phase 5 complete - Email notifications with dynamic subject line and manual test button
