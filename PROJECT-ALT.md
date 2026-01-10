# Server Performance Monitor - WordPress Plugin Project Plan

## Table of Contents

1. [Actionable Checklist](#actionable-checklist)
2. [Executive Summary](#executive-summary)
3. [Technical Architecture](#technical-architecture)
   - 3.1 [Server Metrics Selection](#server-metrics-selection)
   - 3.2 [FSM-Light Pattern](#fsm-light-pattern)
   - 3.3 [Data Flow Architecture](#data-flow-architecture)
4. [Core Components](#core-components)
   - 4.1 [Metrics Collector](#metrics-collector)
   - 4.2 [Data Storage Manager](#data-storage-manager)
   - 4.3 [Email Notifier](#email-notifier)
   - 4.4 [Admin Interface](#admin-interface)
5. [Circuit Breaker Implementation](#circuit-breaker-implementation)
6. [File Structure](#file-structure)
7. [Implementation Phases](#implementation-phases)
   - 7.1 [Phase 1: Core Foundation](#phase-1-core-foundation)
   - 7.2 [Phase 2: Data Collection & Storage](#phase-2-data-collection--storage)
   - 7.3 [Phase 3: Admin Interface](#phase-3-admin-interface)
   - 7.4 [Phase 4: Testing Infrastructure](#phase-4-testing-infrastructure)
   - 7.5 [Phase 5: Production Hardening](#phase-5-production-hardening)
8. [Testing Strategy](#testing-strategy)
9. [Security Considerations](#security-considerations)
10. [Performance Optimization](#performance-optimization)
11. [Code Standards & Documentation](#code-standards--documentation)
12. [Deployment Checklist](#deployment-checklist)
13. [Future Enhancements](#future-enhancements)

---

## Actionable Checklist

### Pre-Development Setup
- [ ] Create Git repository with proper `.gitignore` for WordPress
- [ ] Set up local development environment with WP-CLI
- [ ] Initialize PHPUnit test framework
- [ ] Create plugin scaffold with proper header
- [ ] Set up PHPStan configuration for static analysis

### Phase 1: Core Foundation (Week 1)
- [ ] Create main plugin file with activation/deactivation hooks
- [ ] Implement FSM state manager class
- [ ] Build centralized helper utilities class
- [ ] Create constants definition file
- [ ] Set up autoloader for classes
- [ ] Implement basic error logging system
- [ ] Write unit tests for FSM state transitions
- [ ] Document FSM state diagram

### Phase 2: Data Collection & Storage (Week 1-2)
- [ ] Implement metrics collector class with three metrics
- [ ] Create composite metric calculator
- [ ] Build data storage manager with JSON handling
- [ ] Implement circuit breaker pattern
- [ ] Create CRON job registration and handler
- [ ] Add data pruning for 24-hour retention
- [ ] Write unit tests for metrics collection
- [ ] Write unit tests for circuit breaker
- [ ] Test CRON job execution manually

### Phase 3: Admin Interface (Week 2)
- [ ] Create settings page registration
- [ ] Build admin menu and submenu structure
- [ ] Implement settings link on plugins page
- [ ] Create data display table with sorting
- [ ] Build debug panel showing FSM state and CRON status
- [ ] Implement UTC to local time conversion display
- [ ] Add AJAX handlers for real-time updates
- [ ] Style admin interface with WordPress admin CSS
- [ ] Test admin interface across different screen sizes

### Phase 4: Email Notifications (Week 2)
- [ ] Create email template class
- [ ] Implement email sender with wp_mail wrapper
- [ ] Add email settings (recipient, frequency validation)
- [ ] Build email queue system for reliability
- [ ] Implement email failure retry logic
- [ ] Add email sending to CRON job
- [ ] Test email delivery with different SMTP configurations
- [ ] Write unit tests for email formatting

### Phase 5: Testing Infrastructure (Week 3)
- [ ] Create self-test UI page
- [ ] Build automated test runner for admin
- [ ] Implement PHP unit tests for all core classes
- [ ] Create integration tests for CRON workflow
- [ ] Add tests for circuit breaker edge cases
- [ ] Build mock data generator for testing
- [ ] Create terminal test runner script
- [ ] Document test execution procedures

### Phase 6: Production Hardening (Week 3-4)
- [ ] Implement comprehensive input validation
- [ ] Add nonce verification to all admin actions
- [ ] Create capability checks for all admin pages
- [ ] Implement file permission checks
- [ ] Add error handling for all external operations
- [ ] Create database schema versioning
- [ ] Implement plugin update migration handler
- [ ] Add debug mode toggle (WP_DEBUG integration)
- [ ] Optimize autoloader and class loading
- [ ] Minify admin assets

### Documentation & Deployment
- [ ] Complete PHPDoc comments for all classes/methods
- [ ] Write comprehensive README.md
- [ ] Create user documentation
- [ ] Document troubleshooting procedures
- [ ] Create deployment checklist
- [ ] Prepare plugin for WordPress.org (if applicable)
- [ ] Create changelog documentation
- [ ] Write security disclosure policy

### Post-Launch
- [ ] Monitor error logs for first 48 hours
- [ ] Verify CRON job execution frequency
- [ ] Validate email delivery rates
- [ ] Check JSON file growth patterns
- [ ] Review circuit breaker trigger frequency
- [ ] Collect user feedback on debug panel
- [ ] Plan Phase 2 features based on FSM extensibility

---

## Executive Summary

The **Server Performance Monitor** is a WordPress plugin designed for high-volume WooCommerce sites that need continuous server health monitoring. The plugin follows a finite state machine (FSM-light) architecture to ensure maintainable, extensible code while collecting critical server metrics every 15 minutes.

**Core Value Proposition:**
- Real-time server health visibility for busy e-commerce operations
- Proactive issue detection through continuous monitoring
- Production-ready v1.0 with solid architectural foundation
- Zero technical debt through proper design patterns

**Key Features:**
- Automated metric collection every 15 minutes via WP-CRON
- Composite health score from three server metrics
- 24-hour historical data display in admin interface
- Email notifications for continuous monitoring
- Debug panel for operational visibility
- Circuit breaker protection against runaway processes
- Self-testing infrastructure for reliability verification

**Technical Approach:**
- FSM-light pattern for extensible state management
- Single write paths with centralized helpers
- DRY principles throughout
- Comprehensive PHPDoc documentation
- PHP unit tests with terminal execution support

---

## Technical Architecture

### 3.1 Server Metrics Selection

Based on WordPress/WooCommerce production environments, we'll monitor three critical metrics:

#### Metric 1: Memory Usage
**Rationale:** WooCommerce sites with large product catalogs and heavy query loads can quickly exhaust available memory. Critical for detecting memory leaks and optimization needs.

```
Formula: (memory_get_usage() / memory_get_peak_usage()) * 100
Range: 0-100%
Weight: 35% of composite score
Threshold: Warning at 70%, Critical at 85%
```

#### Metric 2: Database Query Performance
**Rationale:** Slow database queries are the #1 performance killer in WordPress. Monitoring average query time reveals degradation before it impacts customers.

```
Formula: Average query time from $wpdb->queries (if SAVEQUERIES enabled)
Fallback: Response time test query (SELECT 1)
Range: 0-5000ms (capped for scoring)
Weight: 40% of composite score
Threshold: Warning at 100ms, Critical at 250ms
```

#### Metric 3: Filesystem I/O Response
**Rationale:** Disk I/O bottlenecks affect page caching, uploads, and order processing. Essential for detecting storage subsystem issues.

```
Formula: Time to write and read test file to cache directory
Range: 0-1000ms (capped for scoring)
Weight: 25% of composite score
Threshold: Warning at 50ms, Critical at 150ms
```

#### Composite Health Score
```php
/**
 * Composite score calculation:
 * 
 * Score = 100 - (
 *   (memory_pct * 0.35) + 
 *   (normalized_db_time * 0.40) + 
 *   (normalized_io_time * 0.25)
 * )
 * 
 * Result: 0-100 where:
 * - 90-100: Excellent
 * - 75-89: Good
 * - 60-74: Warning
 * - Below 60: Critical
 */
```

### 3.2 FSM-Light Pattern

The plugin uses a lightweight FSM to manage operational states and enable future feature additions without architectural rewrites.

#### State Definitions

```
STATES:
├── IDLE          - Plugin active, waiting for next CRON cycle
├── COLLECTING    - Actively gathering metrics
├── PROCESSING    - Computing composite score
├── STORING       - Writing to JSON file
├── NOTIFYING     - Sending email notification
├── ERROR         - Error state requiring intervention
└── MAINTENANCE   - Manual override for updates/debugging
```

#### State Transitions

```
Valid Transitions:
IDLE → COLLECTING        (CRON trigger)
COLLECTING → PROCESSING  (Metrics gathered successfully)
COLLECTING → ERROR       (Metric collection failure)
PROCESSING → STORING     (Score calculated)
PROCESSING → ERROR       (Calculation failure)
STORING → NOTIFYING      (Data written successfully)
STORING → ERROR          (Write failure / circuit breaker)
NOTIFYING → IDLE         (Email sent, cycle complete)
NOTIFYING → IDLE         (Email failed but logged)
ERROR → IDLE             (Auto-recovery after delay)
MAINTENANCE → IDLE       (Manual state reset)
ANY → MAINTENANCE        (Admin override)
```

#### State Manager Implementation

```php
/**
 * Class NC_SPM_State_Manager
 * 
 * Manages FSM state transitions with validation and logging.
 * Implements single-write-path pattern for state persistence.
 */
class NC_SPM_State_Manager {
    /**
     * Current state
     * 
     * @var string
     */
    private $current_state;

    /**
     * Valid state transitions map
     * 
     * @var array
     */
    private $valid_transitions;

    /**
     * State change history (last 10)
     * 
     * @var array
     */
    private $state_history;

    // Methods: transition(), get_state(), force_state(), is_valid_transition()
}
```

### 3.3 Data Flow Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     WP-CRON (Every 15 min)                  │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              FSM: IDLE → COLLECTING                         │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Metrics Collector                                   │  │
│  │  ├─ Memory Usage                                     │  │
│  │  ├─ Database Performance                             │  │
│  │  └─ Filesystem I/O                                   │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              FSM: COLLECTING → PROCESSING                   │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Composite Score Calculator                          │  │
│  │  └─ Weighted average with normalization             │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              FSM: PROCESSING → STORING                      │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Data Storage Manager                                │  │
│  │  ├─ Circuit Breaker Check                            │  │
│  │  ├─ JSON File Write (UTC timestamp)                  │  │
│  │  ├─ Data Pruning (24hr retention)                    │  │
│  │  └─ Write Verification                               │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ├────────────────┐
                 ▼                ▼
┌────────────────────────┐  ┌────────────────────────────────┐
│  FSM: STORING →        │  │  Admin Interface (Async)       │
│  NOTIFYING             │  │  ├─ Read JSON File              │
│                        │  │  ├─ Convert UTC → Local Time   │
│  ┌──────────────────┐ │  │  ├─ Display Table (24hr data)  │
│  │ Email Notifier   │ │  │  └─ Debug Panel (FSM + CRON)   │
│  │ └─ Send Metrics  │ │  └────────────────────────────────┘
│  └──────────────────┘ │
└────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│              FSM: NOTIFYING → IDLE                          │
│              (Wait for next CRON cycle)                     │
└─────────────────────────────────────────────────────────────┘
```

**Single Write Path:** Only the Data Storage Manager writes to JSON file during STORING state.

**Single Source of Truth:** JSON file is the authoritative data source; all displays read from it.

**Error Handling:** Each state transition includes validation and error recovery to ERROR state.

---

## Core Components

### 4.1 Metrics Collector

**Purpose:** Safely gather server metrics with timeout protection and error handling.

**Key Responsibilities:**
- Execute metric collection with max execution time limits
- Normalize raw metric values to 0-100 scale
- Handle collection failures gracefully
- Log performance data for diagnostics

**Class Structure:**
```php
/**
 * Class NC_SPM_Metrics_Collector
 * 
 * Collects server performance metrics with timeout protection.
 * 
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */
class NC_SPM_Metrics_Collector {
    /**
     * Maximum execution time per metric (seconds)
     */
    const MAX_METRIC_TIME = 5;

    /**
     * Collect all metrics
     * 
     * @return array|WP_Error Array of metrics or error
     */
    public function collect_all_metrics();

    /**
     * Collect memory usage metric
     * 
     * @return float Memory usage percentage (0-100)
     */
    private function collect_memory_metric();

    /**
     * Collect database performance metric
     * 
     * @return float Average query time in milliseconds
     */
    private function collect_database_metric();

    /**
     * Collect filesystem I/O metric
     * 
     * @return float I/O response time in milliseconds
     */
    private function collect_filesystem_metric();

    /**
     * Normalize metric to 0-100 scale
     * 
     * @param float $value Raw metric value
     * @param float $min Minimum expected value
     * @param float $max Maximum expected value
     * @param bool $inverse True if lower is better
     * @return float Normalized value (0-100)
     */
    private function normalize_metric( $value, $min, $max, $inverse = false );
}
```

### 4.2 Data Storage Manager

**Purpose:** Handle all JSON file operations with circuit breaker protection.

**Key Responsibilities:**
- Validate file paths and permissions
- Implement circuit breaker pattern
- Maintain 24-hour data retention
- Ensure atomic write operations
- Verify data integrity

**Class Structure:**
```php
/**
 * Class NC_SPM_Storage_Manager
 * 
 * Manages data persistence with circuit breaker protection.
 * 
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */
class NC_SPM_Storage_Manager {
    /**
     * Maximum JSON file size (bytes) - 5MB
     */
    const MAX_FILE_SIZE = 5242880;

    /**
     * Maximum records to store (24 hours * 4 per hour)
     */
    const MAX_RECORDS = 96;

    /**
     * Circuit breaker failure threshold
     */
    const CB_FAILURE_THRESHOLD = 5;

    /**
     * Circuit breaker cooldown period (seconds)
     */
    const CB_COOLDOWN = 300; // 5 minutes

    /**
     * Store performance data
     * 
     * @param array $data Performance metrics and composite score
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function store_data( $data );

    /**
     * Retrieve stored data
     * 
     * @param int $limit Optional limit on records returned
     * @return array|WP_Error Array of records or error
     */
    public function get_data( $limit = null );

    /**
     * Check circuit breaker state
     * 
     * @return bool True if circuit is open (blocked)
     */
    private function is_circuit_open();

    /**
     * Record circuit breaker failure
     * 
     * @return void
     */
    private function record_cb_failure();

    /**
     * Reset circuit breaker
     * 
     * @return void
     */
    private function reset_circuit_breaker();

    /**
     * Prune old data beyond retention period
     * 
     * @return int Number of records removed
     */
    private function prune_old_data();

    /**
     * Validate file integrity
     * 
     * @return bool True if file is valid
     */
    private function validate_file_integrity();
}
```

### 4.3 Email Notifier

**Purpose:** Send email notifications with retry logic and failure handling.

**Key Responsibilities:**
- Format email content with metrics
- Send via wp_mail with error handling
- Implement retry logic for failures
- Log email delivery status

**Class Structure:**
```php
/**
 * Class NC_SPM_Email_Notifier
 * 
 * Handles email notifications with retry logic.
 * 
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */
class NC_SPM_Email_Notifier {
    /**
     * Maximum email retry attempts
     */
    const MAX_RETRIES = 3;

    /**
     * Retry delay in seconds
     */
    const RETRY_DELAY = 60;

    /**
     * Send performance notification
     * 
     * @param array $metrics Performance metrics and composite score
     * @return bool|WP_Error True on success, WP_Error on final failure
     */
    public function send_notification( $metrics );

    /**
     * Format email body
     * 
     * @param array $metrics Performance data
     * @return string Formatted email HTML
     */
    private function format_email_body( $metrics );

    /**
     * Get email subject line
     * 
     * @param float $composite_score Overall health score
     * @return string Email subject
     */
    private function get_email_subject( $composite_score );

    /**
     * Attempt to send with retries
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @return bool Success status
     */
    private function send_with_retry( $to, $subject, $body );
}
```

### 4.4 Admin Interface

**Purpose:** Display performance data and provide operational visibility.

**Key Responsibilities:**
- Render settings page with data table
- Display debug panel with FSM and CRON status
- Convert UTC timestamps to local time
- Handle AJAX requests for real-time updates
- Provide manual controls for testing

**Class Structure:**
```php
/**
 * Class NC_SPM_Admin_Interface
 * 
 * Manages admin UI and settings pages.
 * 
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */
class NC_SPM_Admin_Interface {
    /**
     * Register admin menu and pages
     * 
     * @return void
     */
    public function register_admin_menu();

    /**
     * Render main settings page
     * 
     * @return void
     */
    public function render_settings_page();

    /**
     * Render debug panel
     * 
     * @return void
     */
    private function render_debug_panel();

    /**
     * Render performance data table
     * 
     * @return void
     */
    private function render_data_table();

    /**
     * Convert UTC timestamp to local time for display
     * 
     * @param string $utc_timestamp UTC timestamp from JSON
     * @return string Formatted local time
     */
    private function convert_to_local_time( $utc_timestamp );

    /**
     * Handle AJAX request for data refresh
     * 
     * @return void
     */
    public function ajax_refresh_data();

    /**
     * Add settings link to plugins page
     * 
     * @param array $links Existing plugin action links
     * @return array Modified links array
     */
    public function add_settings_link( $links );
}
```

---

## Circuit Breaker Implementation

### Question: Do we need a circuit breaker?

**Answer: YES, absolutely critical.**

**Rationale:**

1. **File System Protection:** Without a circuit breaker, repeated write failures (disk full, permission issues, I/O errors) will queue up CRON jobs and potentially crash the site.

2. **Resource Conservation:** Failed writes consume server resources. A circuit breaker prevents cascading failures during infrastructure issues.

3. **Graceful Degradation:** Better to temporarily stop monitoring than to exhaust resources trying to write to a failing storage system.

4. **Production Requirement:** On high-volume WooCommerce sites, any runaway process can impact checkout and order processing.

### Circuit Breaker Pattern

```php
/**
 * Circuit Breaker State Management
 * 
 * States:
 * - CLOSED: Normal operation, writes proceed
 * - OPEN: Failures exceeded threshold, writes blocked
 * - HALF_OPEN: Testing if system recovered
 */

// Stored in wp_options:
// nc_spm_circuit_breaker = [
//     'state' => 'closed|open|half_open',
//     'failure_count' => int,
//     'last_failure' => timestamp,
//     'opened_at' => timestamp,
//     'cooldown_until' => timestamp
// ]

/**
 * Circuit breaker check before write operation
 */
if ( $this->is_circuit_open() ) {
    $this->log_error( 'Circuit breaker OPEN - write operation blocked' );
    return new WP_Error( 'circuit_open', 'Storage writes temporarily disabled' );
}

/**
 * After write failure
 */
$this->record_cb_failure();
if ( $this->get_failure_count() >= self::CB_FAILURE_THRESHOLD ) {
    $this->open_circuit();
    $this->notify_admin_circuit_open();
}

/**
 * Cooldown logic (5 minutes)
 */
if ( time() >= $this->get_cooldown_timestamp() ) {
    $this->set_half_open();
    // Next write attempt will test if system recovered
}
```

### Circuit Breaker Monitoring

The debug panel will display:
- Current circuit breaker state
- Failure count (if any)
- Time until cooldown expires (if open)
- Last failure reason
- Manual reset button (admin only)

---

## File Structure

```
neochrome-server-performance-monitor/
├── neochrome-server-performance-monitor.php    # Main plugin file
├── README.md                                    # Plugin documentation
├── LICENSE.txt                                  # GPL v2+ license
├── composer.json                                # Composer dependencies (PHPUnit)
├── phpunit.xml                                  # PHPUnit configuration
├── .phpstan.neon                               # PHPStan configuration
│
├── includes/
│   ├── class-nc-spm-plugin.php                 # Main plugin class
│   ├── class-nc-spm-activator.php              # Activation handler
│   ├── class-nc-spm-deactivator.php            # Deactivation handler
│   ├── class-nc-spm-constants.php              # Constants definition
│   ├── class-nc-spm-autoloader.php             # Class autoloader
│   │
│   ├── core/
│   │   ├── class-nc-spm-state-manager.php      # FSM implementation
│   │   ├── class-nc-spm-metrics-collector.php  # Metrics collection
│   │   ├── class-nc-spm-storage-manager.php    # Data storage with circuit breaker
│   │   ├── class-nc-spm-email-notifier.php     # Email notifications
│   │   └── class-nc-spm-cron-manager.php       # CRON job management
│   │
│   ├── admin/
│   │   ├── class-nc-spm-admin-interface.php    # Settings page UI
│   │   ├── class-nc-spm-admin-ajax.php         # AJAX handlers
│   │   └── class-nc-spm-self-test.php          # Self-test page
│   │
│   └── helpers/
│       ├── class-nc-spm-helper.php             # Centralized utilities
│       ├── class-nc-spm-logger.php             # Error logging
│       └── class-nc-spm-validator.php          # Input validation
│
├── assets/
│   ├── css/
│   │   └── admin-style.css                     # Admin interface styling
│   ├── js/
│   │   └── admin-script.js                     # Admin interface JavaScript
│   └── images/
│       └── icon.png                            # Plugin icon
│
├── data/
│   └── .gitkeep                                # JSON file storage (not in repo)
│
└── tests/
    ├── bootstrap.php                           # Test bootstrap
    ├── test-runner.php                         # Terminal test runner
    │
    ├── unit/
    │   ├── test-state-manager.php
    │   ├── test-metrics-collector.php
    │   ├── test-storage-manager.php
    │   ├── test-email-notifier.php
    │   └── test-circuit-breaker.php
    │
    └── integration/
        ├── test-cron-workflow.php
        └── test-full-cycle.php
```

---

## Implementation Phases

### Phase 1: Core Foundation (Days 1-2)

**Objective:** Establish plugin structure, FSM, and helper utilities.

**Deliverables:**
1. Main plugin file with WordPress headers
2. Activation/deactivation hooks
3. Autoloader implementation
4. FSM State Manager class
5. Constants definition
6. Helper utilities class
7. Error logging system

**Code Example - Main Plugin File:**
```php
<?php
/**
 * Plugin Name: Neochrome Server Performance Monitor
 * Plugin URI: https://neochrome.io/plugins/server-performance-monitor
 * Description: Monitor server health with automated metrics collection, email notifications, and historical data display for high-volume WooCommerce sites.
 * Version: 1.0.0
 * Author: Neochrome
 * Author URI: https://neochrome.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nc-spm
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'NC_SPM_VERSION', '1.0.0' );
define( 'NC_SPM_PLUGIN_FILE', __FILE__ );
define( 'NC_SPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NC_SPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NC_SPM_DATA_DIR', WP_CONTENT_DIR . '/nc-spm-data/' );

// Autoloader
require_once NC_SPM_PLUGIN_DIR . 'includes/class-nc-spm-autoloader.php';
NC_SPM_Autoloader::register();

// Activation/Deactivation hooks
register_activation_hook( __FILE__, array( 'NC_SPM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NC_SPM_Deactivator', 'deactivate' ) );

// Initialize plugin
function nc_spm_init() {
    $plugin = new NC_SPM_Plugin();
    $plugin->run();
}
add_action( 'plugins_loaded', 'nc_spm_init' );
```

**Testing:**
- Verify plugin activates without errors
- Test autoloader with test class
- Validate FSM state transitions
- Check error logging functionality

---

### Phase 2: Data Collection & Storage (Days 2-4)

**Objective:** Implement metrics collection, composite scoring, storage with circuit breaker.

**Deliverables:**
1. Metrics Collector with three metrics
2. Composite score calculator
3. Storage Manager with JSON handling
4. Circuit breaker implementation
5. CRON job registration
6. Data pruning for 24-hour retention

**Code Example - Storage Manager with Circuit Breaker:**
```php
<?php
/**
 * Class NC_SPM_Storage_Manager
 * 
 * Manages data persistence with circuit breaker protection.
 * Implements single-write-path pattern.
 *
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */
class NC_SPM_Storage_Manager {

    /**
     * JSON file path
     *
     * @var string
     */
    private $file_path;

    /**
     * Circuit breaker option key
     *
     * @var string
     */
    const CB_OPTION = 'nc_spm_circuit_breaker';

    /**
     * Constructor
     */
    public function __construct() {
        $this->file_path = NC_SPM_DATA_DIR . 'performance-data.json';
        $this->ensure_data_directory();
    }

    /**
     * Store performance data
     * 
     * Single write path with circuit breaker protection.
     *
     * @param array $data Performance metrics and composite score
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function store_data( $data ) {
        // Circuit breaker check
        if ( $this->is_circuit_open() ) {
            NC_SPM_Logger::log( 'Storage write blocked by circuit breaker' );
            return new WP_Error( 
                'circuit_open', 
                __( 'Storage writes temporarily disabled due to repeated failures', 'nc-spm' ) 
            );
        }

        // Validate input
        if ( ! $this->validate_data_structure( $data ) ) {
            return new WP_Error( 
                'invalid_data', 
                __( 'Invalid data structure provided', 'nc-spm' ) 
            );
        }

        // Add timestamp
        $data['timestamp'] = gmdate( 'c' ); // ISO 8601 UTC format

        // Read existing data
        $existing_data = $this->read_file();
        if ( is_wp_error( $existing_data ) ) {
            $this->record_cb_failure();
            return $existing_data;
        }

        // Append new data
        $existing_data[] = $data;

        // Prune old data
        $existing_data = $this->prune_old_data( $existing_data );

        // Write atomically
        $result = $this->atomic_write( $existing_data );
        
        if ( is_wp_error( $result ) ) {
            $this->record_cb_failure();
            return $result;
        }

        // Reset circuit breaker on success
        $this->reset_circuit_breaker();

        NC_SPM_Logger::log( 'Performance data stored successfully' );
        return true;
    }

    /**
     * Check if circuit breaker is open
     *
     * @return bool True if circuit is open (writes blocked)
     */
    private function is_circuit_open() {
        $cb_state = get_option( self::CB_OPTION, array(
            'state' => 'closed',
            'failure_count' => 0,
            'opened_at' => null,
            'cooldown_until' => null,
        ) );

        // Check if in cooldown period
        if ( 'open' === $cb_state['state'] ) {
            if ( time() >= $cb_state['cooldown_until'] ) {
                // Cooldown expired, try half-open
                $cb_state['state'] = 'half_open';
                update_option( self::CB_OPTION, $cb_state );
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Record circuit breaker failure
     *
     * @return void
     */
    private function record_cb_failure() {
        $cb_state = get_option( self::CB_OPTION, array(
            'state' => 'closed',
            'failure_count' => 0,
        ) );

        $cb_state['failure_count']++;
        $cb_state['last_failure'] = time();

        // Open circuit if threshold exceeded
        if ( $cb_state['failure_count'] >= NC_SPM_Constants::CB_FAILURE_THRESHOLD ) {
            $cb_state['state'] = 'open';
            $cb_state['opened_at'] = time();
            $cb_state['cooldown_until'] = time() + NC_SPM_Constants::CB_COOLDOWN;

            NC_SPM_Logger::log( 'Circuit breaker OPENED - threshold exceeded' );
            
            // Notify admin
            $this->notify_admin_circuit_open();
        }

        update_option( self::CB_OPTION, $cb_state );
    }

    /**
     * Reset circuit breaker on successful write
     *
     * @return void
     */
    private function reset_circuit_breaker() {
        update_option( self::CB_OPTION, array(
            'state' => 'closed',
            'failure_count' => 0,
            'opened_at' => null,
            'cooldown_until' => null,
        ) );
    }

    /**
     * Prune data older than 24 hours
     *
     * @param array $data Existing performance data
     * @return array Pruned data
     */
    private function prune_old_data( $data ) {
        $cutoff_time = strtotime( '-24 hours' );
        
        return array_filter( $data, function( $record ) use ( $cutoff_time ) {
            $record_time = strtotime( $record['timestamp'] );
            return $record_time >= $cutoff_time;
        } );
    }

    /**
     * Atomic file write operation
     *
     * @param array $data Data to write
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function atomic_write( $data ) {
        $temp_file = $this->file_path . '.tmp';
        
        // Encode JSON
        $json = wp_json_encode( $data, JSON_PRETTY_PRINT );
        if ( false === $json ) {
            return new WP_Error( 
                'json_encode_failed', 
                __( 'Failed to encode data to JSON', 'nc-spm' ) 
            );
        }

        // Write to temp file
        $result = file_put_contents( $temp_file, $json, LOCK_EX );
        if ( false === $result ) {
            return new WP_Error( 
                'write_failed', 
                __( 'Failed to write data to file', 'nc-spm' ) 
            );
        }

        // Atomic rename
        if ( ! rename( $temp_file, $this->file_path ) ) {
            unlink( $temp_file );
            return new WP_Error( 
                'rename_failed', 
                __( 'Failed to finalize data file', 'nc-spm' ) 
            );
        }

        return true;
    }

    // Additional methods: read_file(), validate_data_structure(), 
    // ensure_data_directory(), notify_admin_circuit_open()
}
```

**Testing:**
- Unit tests for each metric collector
- Circuit breaker failure scenarios
- Data pruning edge cases
- CRON job manual trigger
- File permission issues

---

### Phase 3: Admin Interface (Days 4-6)

**Objective:** Build settings page with data table and debug panel.

**Deliverables:**
1. Admin menu registration
2. Settings page template
3. Data display table with reverse chronological order
4. Debug panel showing FSM and CRON status
5. Settings link on plugins page
6. UTC to local time conversion
7. AJAX refresh functionality

**Code Example - Admin Interface:**
```php
<?php
/**
 * Class NC_SPM_Admin_Interface
 * 
 * Manages admin UI and settings pages.
 *
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */
class NC_SPM_Admin_Interface {

    /**
     * Register admin menu
     *
     * @return void
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'Server Performance', 'nc-spm' ),
            __( 'Server Perf', 'nc-spm' ),
            'manage_options',
            'nc-spm-dashboard',
            array( $this, 'render_dashboard_page' ),
            'dashicons-performance',
            80
        );

        add_submenu_page(
            'nc-spm-dashboard',
            __( 'Self Test', 'nc-spm' ),
            __( 'Self Test', 'nc-spm' ),
            'manage_options',
            'nc-spm-self-test',
            array( $this, 'render_self_test_page' )
        );
    }

    /**
     * Render dashboard page
     *
     * @return void
     */
    public function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access', 'nc-spm' ) );
        }

        // Enqueue admin assets
        wp_enqueue_style( 'nc-spm-admin', NC_SPM_PLUGIN_URL . 'assets/css/admin-style.css', array(), NC_SPM_VERSION );
        wp_enqueue_script( 'nc-spm-admin', NC_SPM_PLUGIN_URL . 'assets/js/admin-script.js', array( 'jquery' ), NC_SPM_VERSION, true );

        // Localize script for AJAX
        wp_localize_script( 'nc-spm-admin', 'ncSpmAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'nc_spm_admin' ),
        ) );

        ?>
        <div class="wrap nc-spm-dashboard">
            <h1><?php esc_html_e( 'Server Performance Monitor', 'nc-spm' ); ?></h1>
            
            <?php $this->render_debug_panel(); ?>
            
            <div class="nc-spm-data-section">
                <h2><?php esc_html_e( 'Performance History (24 Hours)', 'nc-spm' ); ?></h2>
                <button id="nc-spm-refresh" class="button">
                    <?php esc_html_e( 'Refresh Data', 'nc-spm' ); ?>
                </button>
                <?php $this->render_data_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render debug panel
     *
     * @return void
     */
    private function render_debug_panel() {
        // Only show if WP_DEBUG is true or admin explicitly enabled
        if ( ! WP_DEBUG && ! get_option( 'nc_spm_show_debug', false ) ) {
            return;
        }

        $state_manager = new NC_SPM_State_Manager();
        $current_state = $state_manager->get_state();
        
        $cron_jobs = wp_get_scheduled_event( 'nc_spm_collect_metrics' );
        $next_run = $cron_jobs ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cron_jobs->timestamp ) : __( 'Not scheduled', 'nc-spm' );

        $cb_state = get_option( 'nc_spm_circuit_breaker', array( 'state' => 'closed' ) );

        ?>
        <div class="nc-spm-debug-panel notice notice-info">
            <h3><?php esc_html_e( 'Debug Information', 'nc-spm' ); ?></h3>
            <table class="widefat">
                <tr>
                    <th><?php esc_html_e( 'FSM State:', 'nc-spm' ); ?></th>
                    <td><code><?php echo esc_html( strtoupper( $current_state ) ); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'CRON Next Run:', 'nc-spm' ); ?></th>
                    <td><?php echo esc_html( $next_run ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Circuit Breaker:', 'nc-spm' ); ?></th>
                    <td>
                        <code><?php echo esc_html( strtoupper( $cb_state['state'] ) ); ?></code>
                        <?php if ( 'open' === $cb_state['state'] ) : ?>
                            <span class="nc-spm-cb-warning">
                                <?php
                                printf(
                                    /* translators: %s: time remaining */
                                    esc_html__( '(Cooldown: %s remaining)', 'nc-spm' ),
                                    esc_html( human_time_diff( time(), $cb_state['cooldown_until'] ) )
                                );
                                ?>
                            </span>
                            <button class="button button-small" id="nc-spm-reset-cb">
                                <?php esc_html_e( 'Reset', 'nc-spm' ); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Data File:', 'nc-spm' ); ?></th>
                    <td>
                        <?php
                        $data_file = NC_SPM_DATA_DIR . 'performance-data.json';
                        if ( file_exists( $data_file ) ) {
                            $file_size = size_format( filesize( $data_file ) );
                            printf(
                                /* translators: %s: file size */
                                esc_html__( 'Exists (%s)', 'nc-spm' ),
                                esc_html( $file_size )
                            );
                        } else {
                            esc_html_e( 'Not created yet', 'nc-spm' );
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render performance data table
     *
     * @return void
     */
    private function render_data_table() {
        $storage = new NC_SPM_Storage_Manager();
        $data = $storage->get_data();

        if ( is_wp_error( $data ) ) {
            ?>
            <div class="notice notice-error">
                <p><?php echo esc_html( $data->get_error_message() ); ?></p>
            </div>
            <?php
            return;
        }

        if ( empty( $data ) ) {
            ?>
            <p><?php esc_html_e( 'No performance data collected yet. Data will appear after the first CRON run.', 'nc-spm' ); ?></p>
            <?php
            return;
        }

        // Reverse chronological order
        $data = array_reverse( $data );

        ?>
        <table class="wp-list-table widefat fixed striped nc-spm-data-table">
            <thead>
                <tr>
                    <th class="nc-spm-col-score"><?php esc_html_e( 'Health Score', 'nc-spm' ); ?></th>
                    <th class="nc-spm-col-memory"><?php esc_html_e( 'Memory', 'nc-spm' ); ?></th>
                    <th class="nc-spm-col-database"><?php esc_html_e( 'Database', 'nc-spm' ); ?></th>
                    <th class="nc-spm-col-filesystem"><?php esc_html_e( 'Filesystem', 'nc-spm' ); ?></th>
                    <th class="nc-spm-col-time"><?php esc_html_e( 'Local Time', 'nc-spm' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $data as $record ) : ?>
                    <?php
                    $score = $record['composite_score'];
                    $score_class = $this->get_score_class( $score );
                    $local_time = $this->convert_to_local_time( $record['timestamp'] );
                    ?>
                    <tr>
                        <td class="nc-spm-score <?php echo esc_attr( $score_class ); ?>">
                            <strong><?php echo esc_html( number_format( $score, 1 ) ); ?></strong>
                            <span class="nc-spm-score-label"><?php echo esc_html( $this->get_score_label( $score ) ); ?></span>
                        </td>
                        <td><?php echo esc_html( number_format( $record['metrics']['memory'], 1 ) . '%' ); ?></td>
                        <td><?php echo esc_html( number_format( $record['metrics']['database'], 1 ) . 'ms' ); ?></td>
                        <td><?php echo esc_html( number_format( $record['metrics']['filesystem'], 1 ) . 'ms' ); ?></td>
                        <td><?php echo esc_html( $local_time ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Convert UTC timestamp to local time
     *
     * Uses WordPress timezone settings for conversion.
     *
     * @param string $utc_timestamp UTC timestamp in ISO 8601 format
     * @return string Formatted local time
     */
    private function convert_to_local_time( $utc_timestamp ) {
        return wp_date(
            get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
            strtotime( $utc_timestamp )
        );
    }

    /**
     * Get CSS class for health score
     *
     * @param float $score Health score (0-100)
     * @return string CSS class name
     */
    private function get_score_class( $score ) {
        if ( $score >= 90 ) {
            return 'nc-spm-excellent';
        } elseif ( $score >= 75 ) {
            return 'nc-spm-good';
        } elseif ( $score >= 60 ) {
            return 'nc-spm-warning';
        }
        return 'nc-spm-critical';
    }

    /**
     * Get human-readable label for health score
     *
     * @param float $score Health score (0-100)
     * @return string Label
     */
    private function get_score_label( $score ) {
        if ( $score >= 90 ) {
            return __( 'Excellent', 'nc-spm' );
        } elseif ( $score >= 75 ) {
            return __( 'Good', 'nc-spm' );
        } elseif ( $score >= 60 ) {
            return __( 'Warning', 'nc-spm' );
        }
        return __( 'Critical', 'nc-spm' );
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Existing plugin action links
     * @return array Modified links array
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=nc-spm-dashboard' ),
            __( 'Settings', 'nc-spm' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }
}
```

**Testing:**
- Verify admin menu appears correctly
- Test data table rendering with sample data
- Validate debug panel information accuracy
- Test AJAX refresh functionality
- Check responsive design
- Verify settings link on plugins page

---

### Phase 4: Testing Infrastructure (Days 7-9)

**Objective:** Build comprehensive testing suite with UI and terminal runners.

**Deliverables:**
1. Self-test admin page
2. PHP unit tests for all core classes
3. Terminal test runner
4. Integration tests for full workflow
5. Mock data generators
6. Test documentation

**Code Example - Self Test Page:**
```php
<?php
/**
 * Class NC_SPM_Self_Test
 * 
 * Self-test UI page with automated test execution.
 *
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */
class NC_SPM_Self_Test {

    /**
     * Render self-test page
     *
     * @return void
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access', 'nc-spm' ) );
        }

        ?>
        <div class="wrap nc-spm-self-test">
            <h1><?php esc_html_e( 'Server Performance Monitor - Self Test', 'nc-spm' ); ?></h1>
            
            <div class="nc-spm-test-controls">
                <button id="nc-spm-run-tests" class="button button-primary">
                    <?php esc_html_e( 'Run All Tests', 'nc-spm' ); ?>
                </button>
                <button id="nc-spm-run-unit-tests" class="button">
                    <?php esc_html_e( 'Unit Tests Only', 'nc-spm' ); ?>
                </button>
                <button id="nc-spm-run-integration-tests" class="button">
                    <?php esc_html_e( 'Integration Tests Only', 'nc-spm' ); ?>
                </button>
            </div>

            <div id="nc-spm-test-results" class="nc-spm-test-results">
                <p><?php esc_html_e( 'Click "Run All Tests" to begin testing.', 'nc-spm' ); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Execute all tests and return results
     *
     * @return array Test results
     */
    public function execute_tests() {
        $results = array(
            'unit' => $this->run_unit_tests(),
            'integration' => $this->run_integration_tests(),
            'summary' => array(),
        );

        // Calculate summary
        $total = 0;
        $passed = 0;
        $failed = 0;

        foreach ( array( 'unit', 'integration' ) as $suite ) {
            foreach ( $results[ $suite ] as $test ) {
                $total++;
                if ( $test['passed'] ) {
                    $passed++;
                } else {
                    $failed++;
                }
            }
        }

        $results['summary'] = array(
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'success_rate' => ( $total > 0 ) ? round( ( $passed / $total ) * 100, 1 ) : 0,
        );

        return $results;
    }

    /**
     * Run unit tests
     *
     * @return array Unit test results
     */
    private function run_unit_tests() {
        return array(
            $this->test_state_manager(),
            $this->test_metrics_collector(),
            $this->test_storage_manager(),
            $this->test_circuit_breaker(),
            $this->test_email_notifier(),
        );
    }

    /**
     * Run integration tests
     *
     * @return array Integration test results
     */
    private function run_integration_tests() {
        return array(
            $this->test_full_collection_cycle(),
            $this->test_cron_workflow(),
            $this->test_data_pruning(),
        );
    }

    /**
     * Test State Manager
     *
     * @return array Test result
     */
    private function test_state_manager() {
        $test = array(
            'name' => 'State Manager - Valid Transitions',
            'passed' => false,
            'message' => '',
        );

        try {
            $manager = new NC_SPM_State_Manager();
            
            // Test valid transition
            $result = $manager->transition( 'collecting' );
            if ( ! $result ) {
                throw new Exception( 'Valid transition failed' );
            }

            // Test invalid transition
            $result = $manager->transition( 'notifying' ); // Should fail from collecting
            if ( $result ) {
                throw new Exception( 'Invalid transition allowed' );
            }

            $test['passed'] = true;
            $test['message'] = 'All state transitions validated correctly';
        } catch ( Exception $e ) {
            $test['message'] = $e->getMessage();
        }

        return $test;
    }

    /**
     * Test Circuit Breaker
     *
     * @return array Test result
     */
    private function test_circuit_breaker() {
        $test = array(
            'name' => 'Circuit Breaker - Failure Threshold',
            'passed' => false,
            'message' => '',
        );

        try {
            $storage = new NC_SPM_Storage_Manager();
            
            // Reset circuit breaker
            delete_option( 'nc_spm_circuit_breaker' );

            // Simulate failures
            for ( $i = 0; $i < NC_SPM_Constants::CB_FAILURE_THRESHOLD; $i++ ) {
                // Trigger failure (e.g., write to non-existent directory)
                $storage->store_data( array() );
            }

            // Circuit should be open now
            if ( ! $this->is_circuit_open_test() ) {
                throw new Exception( 'Circuit breaker did not open after threshold' );
            }

            // Reset for cleanup
            delete_option( 'nc_spm_circuit_breaker' );

            $test['passed'] = true;
            $test['message'] = 'Circuit breaker opened correctly at threshold';
        } catch ( Exception $e ) {
            $test['message'] = $e->getMessage();
        }

        return $test;
    }

    // Additional test methods for other components...
}
```

**Code Example - Terminal Test Runner:**
```php
#!/usr/bin/env php
<?php
/**
 * Terminal test runner for Server Performance Monitor
 * 
 * Usage: php tests/test-runner.php [--suite=unit|integration|all]
 *
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */

// Load WordPress
$wp_load_path = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
    echo "Error: Could not find wp-load.php\n";
    exit( 1 );
}

require_once $wp_load_path;

// Parse arguments
$suite = 'all';
if ( isset( $argv[1] ) && strpos( $argv[1], '--suite=' ) === 0 ) {
    $suite = str_replace( '--suite=', '', $argv[1] );
}

echo "Server Performance Monitor - Test Runner\n";
echo "========================================\n\n";

// Load self-test class
require_once NC_SPM_PLUGIN_DIR . 'includes/admin/class-nc-spm-self-test.php';
$tester = new NC_SPM_Self_Test();

// Execute tests
$results = $tester->execute_tests();

// Display results
echo "Test Results:\n";
echo "-------------\n\n";

$suites_to_run = ( 'all' === $suite ) ? array( 'unit', 'integration' ) : array( $suite );

foreach ( $suites_to_run as $test_suite ) {
    echo strtoupper( $test_suite ) . " TESTS:\n";
    
    foreach ( $results[ $test_suite ] as $test ) {
        $status = $test['passed'] ? '[PASS]' : '[FAIL]';
        $color = $test['passed'] ? "\033[32m" : "\033[31m";
        echo sprintf(
            "%s%s\033[0m %s\n",
            $color,
            $status,
            $test['name']
        );
        
        if ( ! $test['passed'] ) {
            echo "  Error: {$test['message']}\n";
        }
    }
    
    echo "\n";
}

// Summary
echo "Summary:\n";
echo "--------\n";
echo sprintf(
    "Total: %d | Passed: %d | Failed: %d | Success Rate: %s%%\n",
    $results['summary']['total'],
    $results['summary']['passed'],
    $results['summary']['failed'],
    $results['summary']['success_rate']
);

// Exit with appropriate code
exit( $results['summary']['failed'] > 0 ? 1 : 0 );
```

**Testing:**
- Run all unit tests via UI
- Execute terminal test runner
- Verify test coverage for all core classes
- Test failure scenarios
- Validate test result display

---

### Phase 5: Production Hardening (Days 10-12)

**Objective:** Security, performance optimization, and deployment preparation.

**Deliverables:**
1. Input validation and sanitization
2. Nonce verification
3. Capability checks
4. Error handling improvements
5. Performance optimization
6. Debug mode toggle
7. Documentation completion

**Key Security Measures:**

```php
/**
 * Example: Admin AJAX handler with security
 */
public function ajax_refresh_data() {
    // Verify nonce
    if ( ! check_ajax_referer( 'nc_spm_admin', 'nonce', false ) ) {
        wp_send_json_error( array(
            'message' => __( 'Security check failed', 'nc-spm' ),
        ) );
    }

    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array(
            'message' => __( 'Insufficient permissions', 'nc-spm' ),
        ) );
    }

    // Execute refresh
    $storage = new NC_SPM_Storage_Manager();
    $data = $storage->get_data();

    if ( is_wp_error( $data ) ) {
        wp_send_json_error( array(
            'message' => $data->get_error_message(),
        ) );
    }

    wp_send_json_success( array(
        'data' => $data,
    ) );
}
```

---

## Testing Strategy

### Unit Testing

**Tools:** PHPUnit 9.x with WordPress test framework

**Coverage Goals:**
- 100% coverage for FSM state transitions
- 100% coverage for circuit breaker logic
- 90%+ coverage for core classes
- Edge case testing for all validators

**Example Unit Test:**
```php
<?php
/**
 * Class Test_NC_SPM_State_Manager
 * 
 * Unit tests for State Manager.
 *
 * @package Neochrome_Server_Performance_Monitor
 * @since 1.0.0
 */
class Test_NC_SPM_State_Manager extends WP_UnitTestCase {

    /**
     * Test valid state transitions
     */
    public function test_valid_transitions() {
        $manager = new NC_SPM_State_Manager();
        
        // IDLE → COLLECTING
        $result = $manager->transition( 'collecting' );
        $this->assertTrue( $result );
        $this->assertEquals( 'collecting', $manager->get_state() );

        // COLLECTING → PROCESSING
        $result = $manager->transition( 'processing' );
        $this->assertTrue( $result );
        $this->assertEquals( 'processing', $manager->get_state() );
    }

    /**
     * Test invalid state transitions
     */
    public function test_invalid_transitions() {
        $manager = new NC_SPM_State_Manager();
        
        // IDLE → NOTIFYING (invalid)
        $result = $manager->transition( 'notifying' );
        $this->assertFalse( $result );
        $this->assertEquals( 'idle', $manager->get_state() );
    }

    /**
     * Test error state handling
     */
    public function test_error_state_recovery() {
        $manager = new NC_SPM_State_Manager();
        
        // Transition to error
        $manager->transition( 'collecting' );
        $manager->transition( 'error' );
        $this->assertEquals( 'error', $manager->get_state() );

        // Should auto-recover to idle
        $manager->trigger_auto_recovery();
        $this->assertEquals( 'idle', $manager->get_state() );
    }
}
```

### Integration Testing

**Scenarios:**
1. Full collection cycle (CRON → metrics → storage → email)
2. Circuit breaker activation and recovery
3. Data pruning with 24+ hour datasets
4. Admin interface with various data states
5. Email sending with SMTP failures

### Manual Testing Checklist

**Pre-Launch:**
- [ ] Activate plugin on fresh WordPress install
- [ ] Verify CRON job scheduled correctly
- [ ] Trigger manual metric collection
- [ ] Test email delivery with multiple providers
- [ ] Simulate disk full scenario
- [ ] Test with WP_DEBUG enabled
- [ ] Test with various PHP memory limits
- [ ] Verify multi-site compatibility
- [ ] Test deactivation and reactivation
- [ ] Verify data persistence across plugin updates

---

## Security Considerations

### Input Validation

**All user inputs must be validated:**
- Email addresses: `is_email()`
- Numeric values: `absint()` or `floatval()`
- Text fields: `sanitize_text_field()`
- URLs: `esc_url_raw()`

### Output Escaping

**All outputs must be escaped:**
- HTML content: `esc_html()`
- HTML attributes: `esc_attr()`
- URLs: `esc_url()`
- JavaScript: `wp_json_encode()`

### Nonce Protection

**All admin actions require nonces:**
```php
// Generate nonce
$nonce = wp_create_nonce( 'nc_spm_admin_action' );

// Verify nonce
if ( ! wp_verify_nonce( $nonce, 'nc_spm_admin_action' ) ) {
    wp_die( 'Security check failed' );
}
```

### Capability Checks

**All admin pages require capability verification:**
```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Insufficient permissions' );
}
```

### File Operations

**All file operations must validate paths:**
```php
// Validate path is within allowed directory
$allowed_dir = NC_SPM_DATA_DIR;
$real_path = realpath( $file_path );

if ( strpos( $real_path, $allowed_dir ) !== 0 ) {
    return new WP_Error( 'invalid_path', 'File path outside allowed directory' );
}
```

### SQL Queries

**All database queries must use prepared statements:**
```php
// Even though we're using JSON files, any future DB queries must use:
$wpdb->prepare( 
    "SELECT * FROM {$wpdb->prefix}table WHERE id = %d",
    $id 
);
```

---

## Performance Optimization

### Autoloading

**Use optimized autoloader to reduce includes:**
```php
spl_autoload_register( function( $class ) {
    if ( strpos( $class, 'NC_SPM_' ) !== 0 ) {
        return;
    }

    $file = str_replace( '_', '-', strtolower( $class ) );
    $file = str_replace( 'nc-spm-', '', $file );
    
    $paths = array(
        NC_SPM_PLUGIN_DIR . 'includes/class-' . $file . '.php',
        NC_SPM_PLUGIN_DIR . 'includes/core/class-' . $file . '.php',
        NC_SPM_PLUGIN_DIR . 'includes/admin/class-' . $file . '.php',
        NC_SPM_PLUGIN_DIR . 'includes/helpers/class-' . $file . '.php',
    );

    foreach ( $paths as $path ) {
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
} );
```

### CRON Optimization

**Prevent CRON overlap:**
```php
public function collect_metrics_cron() {
    // Check if previous job still running
    if ( get_transient( 'nc_spm_collection_running' ) ) {
        NC_SPM_Logger::log( 'Previous collection still running, skipping' );
        return;
    }

    // Set lock
    set_transient( 'nc_spm_collection_running', true, 300 ); // 5 min timeout

    try {
        // Execute collection
        $this->execute_collection();
    } finally {
        // Always release lock
        delete_transient( 'nc_spm_collection_running' );
    }
}
```

### JSON File Size Management

**Implement hard cap on file size:**
```php
// Before writing, check file size
if ( file_exists( $this->file_path ) ) {
    $current_size = filesize( $this->file_path );
    
    if ( $current_size > self::MAX_FILE_SIZE ) {
        // Emergency prune - keep only last 48 records
        $data = array_slice( $data, -48 );
        NC_SPM_Logger::log( 'Emergency prune triggered - file size exceeded' );
    }
}
```

### Asset Optimization

**Minify and concatenate admin assets:**
- Minify CSS and JavaScript
- Use WordPress asset versioning
- Lazy load non-critical scripts
- Use inline critical CSS

---

## Code Standards & Documentation

### PHPDoc Standard

**All classes, methods, and properties must have PHPDoc comments:**

```php
/**
 * Calculate composite health score from individual metrics
 * 
 * Applies weighted average based on metric importance:
 * - Memory: 35%
 * - Database: 40%
 * - Filesystem: 25%
 *
 * @since 1.0.0
 *
 * @param array $metrics {
 *     Individual metric values
 *
 *     @type float $memory Memory usage percentage (0-100)
 *     @type float $database Average query time in milliseconds
 *     @type float $filesystem I/O response time in milliseconds
 * }
 * @return float Composite health score (0-100)
 */
private function calculate_composite_score( $metrics ) {
    // Implementation
}
```

### WordPress Coding Standards

**Follow WordPress Coding Standards (WPCS):**
- Use WordPress naming conventions
- Follow WordPress indentation (tabs, not spaces)
- Use WordPress functions instead of PHP equivalents
- Follow WordPress hook naming conventions

**Validate with PHPCS:**
```bash
phpcs --standard=WordPress neochrome-server-performance-monitor/
```

### Inline Comments

**Add inline comments for complex logic:**
```php
// Calculate normalized database metric
// Lower is better, so we invert the normalization
$db_normalized = 100 - $this->normalize_metric(
    $raw_db_time,
    0,          // Best case: 0ms
    5000,       // Worst case: 5000ms
    false       // Don't invert (we handle it manually)
);
```

---

## Deployment Checklist

### Pre-Deployment

- [ ] All PHPDoc comments complete
- [ ] PHPCS validation passed
- [ ] PHPStan static analysis passed (level 6+)
- [ ] All unit tests passing
- [ ] All integration tests passing
- [ ] Manual testing completed
- [ ] README.md complete with usage instructions
- [ ] CHANGELOG.md updated
- [ ] Version number updated in plugin header
- [ ] Version number updated in constants

### Deployment Steps

1. **Create deployment package:**
   ```bash
   # Exclude development files
   zip -r nc-spm-v1.0.0.zip neochrome-server-performance-monitor/ \
       -x "*.git*" \
       -x "*node_modules*" \
       -x "*tests*" \
       -x "*.phpcs.xml*" \
       -x "*composer.json*" \
       -x "*composer.lock*"
   ```

2. **Test on staging environment:**
   - Install from zip
   - Activate plugin
   - Configure settings
   - Trigger manual collection
   - Verify CRON scheduling
   - Test email delivery
   - Monitor for 24 hours

3. **Production deployment:**
   - Backup production site
   - Upload plugin zip
   - Activate via WordPress admin
   - Configure settings
   - Monitor logs for first 48 hours
   - Verify CRON execution
   - Test email notifications

### Post-Deployment Monitoring

**First 48 Hours:**
- Monitor error logs every 4 hours
- Verify CRON job execution
- Check email delivery rate
- Monitor JSON file growth
- Watch for circuit breaker triggers
- Review FSM state transitions

**First Week:**
- Analyze performance impact
- Review collected metrics for accuracy
- Gather user feedback
- Check for edge cases in production
- Validate data retention working correctly

---

## Future Enhancements

### Phase 2 Features (FSM Extensions)

**Additional Metrics:**
- CPU load average
- Network latency
- External API response times
- WooCommerce specific metrics (orders per hour, cart abandonment)

**Enhanced Alerting:**
- Threshold-based email triggers
- Slack/Discord webhook notifications
- SMS alerts for critical issues
- Escalation policies

**Advanced Monitoring:**
- Historical trend analysis
- Anomaly detection using statistical models
- Predictive alerts based on trends
- Correlation analysis between metrics

### Phase 3 Features

**Dashboard Enhancements:**
- Real-time charts with Chart.js
- Comparative analysis (week over week)
- Export data to CSV
- Custom metric thresholds per site

**Multi-Site Support:**
- Network-wide monitoring
- Centralized dashboard
- Per-site or network-level notifications
- Aggregate health scores

**Integration Extensions:**
- REST API for external monitoring tools
- Third-party service integrations (New Relic, Datadog)
- Custom webhook support
- Zapier integration

---

## Conclusion

This project plan provides a comprehensive roadmap for building a production-ready WordPress Server Performance Monitor plugin. The FSM-light architecture ensures extensibility for future enhancements while maintaining code quality from day one.

**Key Success Factors:**
1. Circuit breaker protection prevents runaway processes
2. Single write path ensures data integrity
3. Comprehensive testing validates reliability
4. Debug panel provides operational visibility
5. Security best practices protect production sites
6. WordPress coding standards ensure maintainability

**Next Steps:**
1. Review and approve project plan
2. Set up development environment
3. Begin Phase 1 implementation
4. Schedule weekly progress reviews
5. Plan Phase 2 features based on production feedback

---

**Project Timeline Summary:**
- Week 1: Core foundation and data collection (Phases 1-2)
- Week 2: Admin interface and email notifications (Phases 3-4)
- Week 3-4: Testing infrastructure and production hardening (Phases 5-6)
- Week 4: Documentation and deployment preparation

**Estimated Total Time:** 3-4 weeks for v1.0 production release

---

*Document Version: 1.0*  
*Created: January 10, 2026*  
*Author: Neochrome Development Team*
