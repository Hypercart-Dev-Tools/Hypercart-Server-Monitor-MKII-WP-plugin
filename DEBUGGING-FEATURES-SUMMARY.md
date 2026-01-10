# Debugging & Visibility Features Summary

## Overview
This document summarizes the new debugging and visibility features added to the WP Server Performance Monitor plugin to provide "glass box" transparency without requiring SSH access.

---

## ✅ Logging Confirmation

**Question:** Does `Hypercart_Logger` accumulate entries into a single daily log file?

**Answer:** **YES!**

- **File naming:** `hypercart-YYYY-MM-DD.log` (e.g., `hypercart-2026-01-10.log`)
- **Behavior:** Each 15-minute run appends new log entries to the current day's file
- **Daily rotation:** At midnight UTC, a new file is created for the new day
- **Retention:** Files persist indefinitely (no automatic deletion) for manual review/archiving
- **Accumulation:** All entries from all 15-minute runs accumulate in the same file throughout the day

**Example timeline:**
- 9:00 AM → logs to `hypercart-2026-01-10.log`
- 9:15 AM → appends to same `hypercart-2026-01-10.log`
- 9:30 AM → appends to same `hypercart-2026-01-10.log`
- ...continues all day...
- Next day 12:01 AM UTC → new file `hypercart-2026-01-11.log`

---

## 🆕 New Features Added

### 1. Manual Test Runner (Section 8.1)

**Purpose:** Run metrics collection and scoring WITHOUT persisting to JSON or sending email.

**Features:**
- Button: "Run Manual Test" (nonce-protected, AJAX)
- Displays:
  - Timestamp (local time via `Hypercart_Time::format()`)
  - Combined score with label (Excellent/Good/Warning/Critical)
  - Raw metrics breakdown (CPU, Memory, Disk)
  - Collection duration (ms)
  - Any warnings or fallbacks
  - Color-coded score indicator (green/yellow/orange/red)
  - **Optional:** "Show JSON Preview" toggle to see exact JSON structure that would be saved

**Benefits:**
- ✅ See current metrics instantly without waiting for cron
- ✅ Test scoring logic without polluting data
- ✅ Verify collectors are working
- ✅ No side effects (no JSON write, no email)
- ✅ See what's being saved without reading files

**Implementation:**
- AJAX endpoint: `wp_ajax_wp_server_monitor_manual_test`
- Calls collectors + scorer but skips repository write and email
- Logs to `Hypercart_Logger::debug()` only (if WP_DEBUG enabled)

---

### 2. Today's Log Viewer (Section 8.3)

**Purpose:** Display current day's log entries in readable table format (no SSH needed).

**Important:** "Today" is based on **WordPress configured timezone** (not UTC).

**Features:**
- Table columns:
  1. **Time** (local time, HH:MM:SS format)
  2. **Level** (INFO/WARNING/ERROR/DEBUG) with color coding
  3. **Message** (truncated with "show more" for long messages)
  4. **Context** (JSON data, collapsible)
- Filter by level (show all, errors only, warnings+errors, etc.)
- Auto-refresh option (every 30 seconds)
- "Download Today's Log" button (sends file as download)
- Shows entry count and file size
- Reverse chronological (newest first)
- Only shows entries for `wp-server-monitor` plugin

**Implementation:**
- Read from: `WP_CONTENT_DIR/hypercart-logs/hypercart-{TODAY}.log`
- Use `Hypercart_Time::get_today_utc_date()` to get correct log filename
- Parse log format: `[YYYY-MM-DD HH:MM:SS UTC] wp-server-monitor LEVEL: Message {"context":"data"}`
- Convert UTC timestamps to local time for display
- Handle large files gracefully (read last N lines, e.g. 500)
- Cache parsed entries for 30 seconds to avoid repeated file reads

**Benefits:**
- ✅ See today's operational logs without SSH
- ✅ Filter by severity
- ✅ Download for offline analysis
- ✅ All timestamps in local time

---

### 3. Enhanced Debug Panel (Section 8.4)

**New additions:**
- **"View File" button:** Opens modal showing raw JSON content
  - Syntax-highlighted and formatted
  - Read-only
  - "Copy to Clipboard" button
  - Allows inspection without SSH/FTP
- **Log file status:**
  - Today's log file path
  - Size (KB)
  - Entry count (for wp-server-monitor)
  - "View Logs" link (jumps to Log Viewer section)
- **WordPress timezone setting** (for reference)

**Benefits:**
- ✅ Inspect raw JSON data without file access
- ✅ Quick link to log viewer
- ✅ See file sizes and entry counts

---

### 4. Enhanced Self Test (Section 11)

**New checks added:**
- ✅ Log directory exists and is writable
- ✅ Validate log parsing (read today's log file)
- ✅ WP timezone setting vs. server timezone
- ✅ File permissions on key directories

**New output features:**
- Execution time for each check
- "Export Diagnostics" button (downloads full report as .txt file)

---

## 🔧 Hypercart Helper Enhancement Needed

### New Method: `Hypercart_Time::get_today_utc_date()`

**Purpose:** Get the UTC date string for "today" in WordPress timezone.

**Why needed:** Log files are named with UTC dates, but we want to show "today's" logs based on WP timezone.

**Example implementation:**
```php
public static function get_today_utc_date() {
    $wp_now = current_time('timestamp'); // WP timezone
    $utc_timestamp = get_gmt_from_date(date('Y-m-d H:i:s', $wp_now), 'U');
    return gmdate('Y-m-d', $utc_timestamp);
}
```

**Example behavior:**
- If WP timezone is PST and it's 2026-01-10 11pm PST → returns "2026-01-10"
- If WP timezone is PST and it's 2026-01-10 1am PST → returns "2026-01-09" (previous day in UTC)

---

## 📊 Additional Debugging Suggestions (Section 12)

### Recommended (Optional):
1. **Quick Stats Dashboard Widget** - At-a-glance status on WP dashboard
2. **Admin Bar Indicator** - Color-coded icon always visible when logged in
3. **Email Log Viewer** - See recent emails sent/failed
4. **Metric History Chart** (Future) - Visual trend analysis using `Hypercart_Charts`
5. **Export/Import Settings** - Backup and clone configurations

### What NOT to Build (Avoiding Over-Engineering):
❌ Custom log rotation (use Hypercart_Logger's built-in)
❌ Complex alerting system (future phase)
❌ Real-time websocket updates (overkill for 15-min intervals)
❌ Custom database tables (JSON file is sufficient)
❌ Multi-site aggregation (out of scope)
❌ Historical data beyond 24h in JSON (use logs for history)

---

## 🎯 Debugging Philosophy

**Goal:** Make the plugin "glass box" instead of "black box"

✅ **DO:**
- Show what's happening in real-time
- Allow inspection without technical tools
- Provide actionable diagnostics
- Keep UI clean and organized

❌ **DON'T:**
- Duplicate functionality
- Add features "just in case"
- Over-engineer solutions
- Create separate logging systems

---

## 📝 Updated File Structure

**New files added:**
- `src/Admin/ManualTestController.php` - AJAX endpoint for Manual Test Runner
- `src/Admin/LogViewerController.php` - Parse and display log files
- `src/Helpers/LogParser.php` - Parse Hypercart log files

---

## ✅ Updated Acceptance Criteria

**New requirements:**
- ✅ Manual Test Runner works (run metrics without logging)
- ✅ Today's Log Viewer works (display log entries in table)
- ✅ Debug Panel includes "View File" button
- ✅ All timestamps displayed in WordPress configured timezone
- ✅ Can view current metrics without SSH
- ✅ Can view today's logs in admin UI
- ✅ Can view raw JSON data file in modal
- ✅ Can see what's being saved (JSON preview)

---

## 🚀 Implementation Priority

**Phase 4 now includes:**
1. Manual Test Runner (AJAX endpoint)
2. 24-Hour Metrics Table (existing)
3. Today's Log Viewer (NEW)
4. Debug Panel with "View File" button (NEW)
5. Time conversion using `Hypercart_Time::format()` for all displays

---

## 📌 Key Takeaways

1. **Two separate file systems:**
   - JSON data file: Metrics only, 24h rolling window
   - Log files: Operational events, indefinite retention

2. **All timestamps in local time:**
   - Use `Hypercart_Time::format()` for display
   - Store in UTC, display in WP timezone

3. **No SSH required:**
   - View metrics via Manual Test Runner
   - View logs via Log Viewer
   - View raw JSON via "View File" button
   - Download logs via "Download" button

4. **Glass box transparency:**
   - See what's happening
   - See what's being saved
   - See operational logs
   - All in admin UI

---

**Document updated:** 2026-01-10
**Plugin:** WP Server Performance Monitor
**Integration:** Hypercart Helper v1.0.0+

