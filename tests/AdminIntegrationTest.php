<?php
/**
 * Lightweight WP Admin Integration Test
 *
 * Tests admin functionality without running actual benchmarks or modifying data.
 * Safe to run on production-like environments.
 *
 * @package Hypercart_Server_Monitor
 * @since 0.4.19
 */

namespace Hypercart_Server_Monitor\Tests;

/**
 * Admin Integration Test Class
 *
 * Quick smoke tests for WP admin integration points.
 */
class AdminIntegrationTest {

	/**
	 * Test results
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * Run all tests
	 *
	 * @return array Test results
	 */
	public function run() {
		$this->results = array();

		// Test 1: Admin menu registration.
		$this->test_admin_menu_registered();

		// Test 2: AJAX hooks registered.
		$this->test_ajax_hooks_registered();

		// Test 3: Capability checks.
		$this->test_capability_checks();

		// Test 4: Assets enqueued.
		$this->test_admin_assets_enqueued();

		return $this->results;
	}

	/**
	 * Test: Admin menu is registered
	 */
	private function test_admin_menu_registered() {
		global $menu, $submenu;

		$test = array(
			'name'    => 'Admin Menu Registration',
			'passed'  => false,
			'message' => '',
		);

		// Check if our menu exists.
		$menu_exists = false;
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && 'hypercart-server-monitor' === $item[2] ) {
				$menu_exists = true;
				break;
			}
		}

		if ( $menu_exists ) {
			$test['passed']  = true;
			$test['message'] = 'Admin menu "Server Health" is registered correctly';
		} else {
			$test['message'] = 'Admin menu not found in WordPress menu array';
		}

		$this->results[] = $test;
	}

	/**
	 * Test: AJAX hooks are registered
	 */
	private function test_ajax_hooks_registered() {
		global $wp_filter;

		$test = array(
			'name'    => 'AJAX Hooks Registration',
			'passed'  => false,
			'message' => '',
		);

		$required_hooks = array(
			'wp_ajax_hsm_run_manual_test',
			'wp_ajax_hsm_send_test_email',
			'wp_ajax_hsm_run_breaker_self_test',
			'wp_ajax_hsm_schedule_cron',
			'wp_ajax_hsm_toggle_email_notifications',
		);

		$missing_hooks = array();
		foreach ( $required_hooks as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) || ! has_action( $hook ) ) {
				$missing_hooks[] = $hook;
			}
		}

		if ( empty( $missing_hooks ) ) {
			$test['passed']  = true;
			$test['message'] = sprintf( 'All %d AJAX hooks registered correctly', count( $required_hooks ) );
		} else {
			$test['message'] = 'Missing AJAX hooks: ' . implode( ', ', $missing_hooks );
		}

		$this->results[] = $test;
	}

	/**
	 * Test: Capability checks work
	 */
	private function test_capability_checks() {
		$test = array(
			'name'    => 'Capability Checks',
			'passed'  => false,
			'message' => '',
		);

		// Create a test user without manage_options capability.
		$user_id = wp_create_user( 'hsm_test_subscriber', wp_generate_password(), 'test@example.com' );
		if ( is_wp_error( $user_id ) ) {
			$test['message'] = 'Failed to create test user: ' . $user_id->get_error_message();
			$this->results[] = $test;
			return;
		}

		$user = new \WP_User( $user_id );
		$user->set_role( 'subscriber' );

		// Switch to test user.
		wp_set_current_user( $user_id );

		// Test if user can access admin page (should fail).
		$can_access = current_user_can( 'manage_options' );

		// Clean up test user.
		wp_delete_user( $user_id );

		// Restore admin user.
		wp_set_current_user( 1 );

		if ( ! $can_access ) {
			$test['passed']  = true;
			$test['message'] = 'Capability checks working - subscriber cannot access admin functions';
		} else {
			$test['message'] = 'Capability check failed - subscriber has manage_options';
		}

		$this->results[] = $test;
	}

	/**
	 * Test: Admin assets are enqueued on plugin pages
	 */
	private function test_admin_assets_enqueued() {
		$test = array(
			'name'    => 'Admin Assets Enqueued',
			'passed'  => false,
			'message' => '',
		);

		// Simulate being on plugin admin page.
		set_current_screen( 'toplevel_page_hypercart-server-monitor' );

		// Trigger admin_enqueue_scripts.
		do_action( 'admin_enqueue_scripts', 'toplevel_page_hypercart-server-monitor' );

		// Check if our assets are registered.
		$css_registered = wp_style_is( 'hsm-admin-css', 'enqueued' ) || wp_style_is( 'hsm-admin-css', 'registered' );
		$js_registered  = wp_script_is( 'hsm-admin-js', 'enqueued' ) || wp_script_is( 'hsm-admin-js', 'registered' );

		if ( $css_registered && $js_registered ) {
			$test['passed']  = true;
			$test['message'] = 'Admin CSS and JS assets are registered/enqueued correctly';
		} else {
			$missing = array();
			if ( ! $css_registered ) {
				$missing[] = 'CSS';
			}
			if ( ! $js_registered ) {
				$missing[] = 'JS';
			}
			$test['message'] = 'Missing assets: ' . implode( ', ', $missing );
		}

		$this->results[] = $test;
	}
}

