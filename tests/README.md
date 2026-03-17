# Hypercart Server Monitor - Tests

## Admin Integration Tests

Lightweight smoke tests for WordPress admin functionality.

### What These Tests Do

✅ **Safe & Quick** - No benchmarks run, no data modified  
✅ **Real WP Integration** - Tests actual WordPress hooks and APIs  
✅ **Meaningful** - Catches admin registration issues  

### Tests Included

1. **Admin Menu Registration** - Verifies "Server Health" menu exists
2. **AJAX Hooks Registration** - Confirms all 5 AJAX endpoints are hooked
3. **Capability Checks** - Validates `manage_options` requirement works
4. **Admin Assets Enqueued** - Ensures CSS/JS load on plugin pages

### Running the Tests

#### Option 1: WP-CLI (Recommended)

```bash
# From plugin root directory
wp eval-file tests/run-admin-test.php
```

#### Option 2: Direct PHP Execution

```bash
# From WordPress root
php wp-content/plugins/Hypercart-Server-Monitor-MKII/tests/run-admin-test.php
```

#### Option 3: Add to Admin UI (Future Enhancement)

Add a "Run Tests" button to the Debug tab that calls:

```php
require_once HYPERCART_SERVER_MONITOR_PLUGIN_DIR . 'tests/run-admin-test.php';
hsm_run_admin_integration_tests();
```

### Expected Output

```
========================================
  Hypercart Server Monitor
  Admin Integration Tests
========================================

✅ PASS - Admin Menu Registration
   Admin menu "Server Health" is registered correctly

✅ PASS - AJAX Hooks Registration
   All 5 AJAX hooks registered correctly

✅ PASS - Capability Checks
   Capability checks working - subscriber cannot access admin functions

✅ PASS - Admin Assets Enqueued
   Admin CSS and JS assets are registered/enqueued correctly

========================================
Total: 4 | Passed: 4 | Failed: 0
========================================

🎉 All tests passed!
```

### Why These Tests Are Safe

- **No database writes** (except temporary test user creation/deletion)
- **No benchmark execution** (just checks if hooks exist)
- **No email sending** (just verifies AJAX endpoints are registered)
- **No file operations** (just checks WordPress internal state)
- **Quick execution** (< 1 second)

### What These Tests Catch

❌ Admin menu not registered (typo in hook)  
❌ AJAX endpoints missing (forgot to add action)  
❌ Capability checks bypassed (security issue)  
❌ Assets not loading (broken admin UI)  

### Future Enhancements

- Add test for cron job registration
- Add test for options table structure
- Add test for FSM state transitions (unit test)
- Add test for email template rendering (no actual send)

---

**Last Updated:** v0.4.19 - 2026-01-31

