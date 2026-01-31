<?php
/**
 * Test Runner for Admin Integration Tests
 *
 * Usage:
 *   wp eval-file tests/run-admin-test.php
 *
 * Or add to admin page as a "Run Tests" button.
 *
 * @package Hypercart_Server_Monitor
 * @since 0.4.19
 */

// Ensure WordPress is loaded.
if ( ! defined( 'ABSPATH' ) ) {
	// Try to load WordPress if running from CLI.
	$wp_load_path = dirname( __FILE__, 5 ) . '/wp-load.php';
	if ( file_exists( $wp_load_path ) ) {
		require_once $wp_load_path;
	} else {
		die( "Error: WordPress not loaded. Run via WP-CLI: wp eval-file tests/run-admin-test.php\n" );
	}
}

// Load the test class.
require_once __DIR__ . '/AdminIntegrationTest.php';

use Hypercart_Server_Monitor\Tests\AdminIntegrationTest;

/**
 * Run the tests and display results
 */
function hsm_run_admin_integration_tests() {
	echo "\n";
	echo "========================================\n";
	echo "  Hypercart Server Monitor\n";
	echo "  Admin Integration Tests\n";
	echo "========================================\n\n";

	// Check if user has proper permissions (if running in admin context).
	if ( defined( 'WP_ADMIN' ) && ! current_user_can( 'manage_options' ) ) {
		echo "❌ Error: You must have 'manage_options' capability to run tests.\n\n";
		return;
	}

	// Run tests.
	$tester  = new AdminIntegrationTest();
	$results = $tester->run();

	// Display results.
	$passed = 0;
	$failed = 0;

	foreach ( $results as $result ) {
		$status = $result['passed'] ? '✅ PASS' : '❌ FAIL';
		echo sprintf( "%s - %s\n", $status, $result['name'] );
		echo sprintf( "   %s\n\n", $result['message'] );

		if ( $result['passed'] ) {
			$passed++;
		} else {
			$failed++;
		}
	}

	// Summary.
	echo "========================================\n";
	echo sprintf( "Total: %d | Passed: %d | Failed: %d\n", count( $results ), $passed, $failed );
	echo "========================================\n\n";

	if ( 0 === $failed ) {
		echo "🎉 All tests passed!\n\n";
		return true;
	} else {
		echo "⚠️  Some tests failed. Review output above.\n\n";
		return false;
	}
}

// Run tests.
$success = hsm_run_admin_integration_tests();

// Exit with appropriate code if running from CLI.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	exit( $success ? 0 : 1 );
}

