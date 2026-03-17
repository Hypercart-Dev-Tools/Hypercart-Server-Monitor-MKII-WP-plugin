<?php
/**
 * Admin Controller
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Admin;

use Hypercart_Server_Monitor\Domain\FsmStateStore;
use Hypercart_Server_Monitor\Persistence\HealthRepository;
use Hypercart_Server_Monitor\Services\SchedulerService;
use Hypercart_Server_Monitor\Helpers\LockHelper;

/**
 * Admin UI controller using Hypercart_Admin_Tabs.
 */
class AdminController {
	/**
	 * Page slug.
	 */
	const PAGE_SLUG = 'hypercart-server-monitor';

	/**
	 * FSM state store.
	 *
	 * @var FsmStateStore
	 */
	private $state_store;

	/**
	 * Health repository.
	 *
	 * @var HealthRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param FsmStateStore    $state_store FSM state store.
	 * @param HealthRepository $repository  Health repository.
	 */
	public function __construct( FsmStateStore $state_store, HealthRepository $repository ) {
		$this->state_store = $state_store;
		$this->repository  = $repository;
	}

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_hsm_run_manual_test', array( $this, 'ajax_run_manual_test' ) );
		add_action( 'wp_ajax_hsm_send_test_email', array( $this, 'ajax_send_test_email' ) );
		add_action( 'wp_ajax_hsm_toggle_email_notifications', array( $this, 'ajax_toggle_email_notifications' ) );
		add_action( 'wp_ajax_hsm_run_breaker_self_test', array( $this, 'ajax_run_breaker_self_test' ) );
		add_action( 'wp_ajax_hsm_schedule_cron', array( $this, 'ajax_schedule_cron' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		$page_title = sprintf(
			/* translators: %s: plugin version */
			__( 'Hypercart - Server Monitor MKII v%s', 'hypercart-server-monitor' ),
			HYPERCART_SERVER_MONITOR_VERSION
		);
		$menu_title = __( 'Hypercart - Server Monitor MKII', 'hypercart-server-monitor' );
		add_menu_page(
			$page_title,
			$menu_title,
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-performance',
			100
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only enqueue on our admin page.
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		// Enqueue Hypercart Admin Tabs assets.
		if ( class_exists( 'Hypercart_Admin_Tabs' ) ) {
			\Hypercart_Admin_Tabs::enqueue_assets();
		}

		// Enqueue Hypercart Charts assets.
		if ( class_exists( 'Hypercart_Charts' ) ) {
			\Hypercart_Charts::register_assets();
			\Hypercart_Charts::enqueue( array( 'context' => 'hypercart-server-monitor-admin' ) );
		}

		// Enqueue shared styles (used by both admin and frontend).
		wp_enqueue_style(
			'hypercart-server-monitor-shared',
			plugins_url( 'assets/shared.css', HYPERCART_SERVER_MONITOR_PLUGIN_FILE ),
			array(),
			HYPERCART_SERVER_MONITOR_VERSION
		);

		// Enqueue custom admin styles (depends on shared).
		wp_enqueue_style(
			'hypercart-server-monitor-admin',
			plugins_url( 'assets/admin.css', HYPERCART_SERVER_MONITOR_PLUGIN_FILE ),
			array( 'hypercart-server-monitor-shared' ),
			HYPERCART_SERVER_MONITOR_VERSION
		);

		// Add inline CSS for font customization.
		wp_add_inline_style( 'hypercart-server-monitor-shared', $this->get_custom_font_css() );

		// Enqueue WordPress color picker.
		wp_enqueue_style( 'wp-color-picker' );

		// Enqueue custom admin scripts.
		wp_enqueue_script(
			'hypercart-server-monitor-admin',
			plugins_url( 'assets/admin.js', HYPERCART_SERVER_MONITOR_PLUGIN_FILE ),
			array( 'jquery', 'wp-color-picker' ),
			HYPERCART_SERVER_MONITOR_VERSION,
			true
		);

		// Localize script for AJAX.
		wp_localize_script(
			'hypercart-server-monitor-admin',
			'hsmAdmin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'restUrl'    => rest_url(),
				'nonce'      => wp_create_nonce( 'hsm_manual_test' ),
				'emailNonce' => wp_create_nonce( 'hsm_send_test_email' ),
				'debugNonce' => wp_create_nonce( 'hsm_debug' ),
			)
		);
	}

	/**
	 * Generate custom CSS for font settings.
	 *
	 * @return string Custom CSS.
	 */
	private function get_custom_font_css() {
		$settings = get_option( 'hypercart_server_monitor_font_settings', array() );
		$defaults = array(
			'timestamp_size'   => '14',
			'timestamp_weight' => '400',
			'timestamp_color'  => '#000000',
			'benchmark_size'   => '14',
			'benchmark_weight' => '400',
			'benchmark_color'  => '#000000',
			'health_size'      => '12',
			'health_weight'    => '600',
			'health_healthy'   => '#059669',
			'health_unhealthy' => '#dc2626',
		);
		$s        = wp_parse_args( $settings, $defaults );

			// Defense-in-depth: sanitize settings again at render time.
			$s['timestamp_size']   = max( 8, min( 32, absint( $s['timestamp_size'] ) ) );
			$s['benchmark_size']   = max( 8, min( 32, absint( $s['benchmark_size'] ) ) );
			$s['health_size']      = max( 8, min( 32, absint( $s['health_size'] ) ) );
			$s['timestamp_weight'] = $this->sanitize_font_weight( $s['timestamp_weight'], $defaults['timestamp_weight'] );
			$s['benchmark_weight'] = $this->sanitize_font_weight( $s['benchmark_weight'], $defaults['benchmark_weight'] );
			$s['health_weight']    = $this->sanitize_font_weight( $s['health_weight'], $defaults['health_weight'] );
			$s['timestamp_color']  = sanitize_hex_color( $s['timestamp_color'] ) ?: $defaults['timestamp_color'];
			$s['benchmark_color']  = sanitize_hex_color( $s['benchmark_color'] ) ?: $defaults['benchmark_color'];
			$s['health_healthy']   = sanitize_hex_color( $s['health_healthy'] ) ?: $defaults['health_healthy'];
			$s['health_unhealthy'] = sanitize_hex_color( $s['health_unhealthy'] ) ?: $defaults['health_unhealthy'];

			$css = "
			/* Custom Font Settings - Generated from Settings Tab */
			.hsm-table-timestamp,
			.hsm-timestamp-value {
				font-size: {$s['timestamp_size']}px !important;
			font-weight: {$s['timestamp_weight']} !important;
			color: {$s['timestamp_color']} !important;
		}
		.hsm-table-value {
			font-size: {$s['benchmark_size']}px !important;
			font-weight: {$s['benchmark_weight']} !important;
			color: {$s['benchmark_color']} !important;
		}
		.hsm-cron-health,
		.hsm-table-cron-health {
			font-size: {$s['health_size']}px !important;
			font-weight: {$s['health_weight']} !important;
		}
		.hsm-cron-health.healthy,
		.hsm-table-cron-health.healthy {
			color: {$s['health_healthy']} !important;
		}
		.hsm-cron-health.unhealthy,
		.hsm-table-cron-health.unhealthy {
			color: {$s['health_unhealthy']} !important;
		}
		";

		return $css;
	}

	/**
	 * Render admin page using Hypercart_Admin_Tabs.
	 */
	public function render_page() {
		if ( ! class_exists( 'Hypercart_Admin_Tabs' ) ) {
			$this->render_missing_helper_notice();
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( sprintf(
			/* translators: %s: plugin version */
			__( 'Hypercart - Server Monitor MKII v%s', 'hypercart-server-monitor' ),
			HYPERCART_SERVER_MONITOR_VERSION
		) ) . '</h1>';

		\Hypercart_Admin_Tabs::render(
			self::PAGE_SLUG,
			array(
				'default_tab' => 'dashboard',
				'tabs'        => array(
					array(
						'id'              => 'dashboard',
						'label'           => __( 'Dashboard', 'hypercart-server-monitor' ),
						'icon'            => 'dashicons-dashboard',
						'capability'      => 'manage_options',
						'render_callback' => array( $this, 'render_tab_dashboard' ),
					),
					array(
						'id'              => 'manual-test',
						'label'           => __( 'Manual Test', 'hypercart-server-monitor' ),
						'icon'            => 'dashicons-controls-play',
						'capability'      => 'manage_options',
						'render_callback' => array( $this, 'render_tab_manual_test' ),
					),
					array(
						'id'              => 'email',
						'label'           => __( 'Email', 'hypercart-server-monitor' ),
						'icon'            => 'dashicons-email',
						'capability'      => 'manage_options',
						'render_callback' => array( $this, 'render_tab_email' ),
					),
					array(
						'id'              => 'logs',
						'label'           => __( 'Logs', 'hypercart-server-monitor' ),
						'icon'            => 'dashicons-media-text',
						'capability'      => 'manage_options',
						'render_callback' => array( $this, 'render_tab_logs' ),
					),
					array(
						'id'              => 'debug',
						'label'           => __( 'Debug', 'hypercart-server-monitor' ),
						'icon'            => 'dashicons-admin-tools',
						'capability'      => 'manage_options',
						'render_callback' => array( $this, 'render_tab_debug' ),
					),
					array(
						'id'              => 'settings',
						'label'           => __( 'Settings', 'hypercart-server-monitor' ),
						'icon'            => 'dashicons-admin-settings',
						'capability'      => 'manage_options',
						'render_callback' => array( $this, 'render_tab_settings' ),
					),
				),
			)
		);

		echo '</div>';
	}

	/**
	 * Render missing helper notice.
	 */
	private function render_missing_helper_notice() {
		?>
		<div class="wrap">
			<h1>
				<?php
				echo esc_html( sprintf(
					/* translators: %s: plugin version */
					__( 'Hypercart - Server Monitor MKII v%s', 'hypercart-server-monitor' ),
					HYPERCART_SERVER_MONITOR_VERSION
				) );
				?>
			</h1>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Hypercart Helper Required', 'hypercart-server-monitor' ); ?></strong>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: required version */
						esc_html__( 'This plugin requires Hypercart Helper v%s or higher with Admin Tabs support.', 'hypercart-server-monitor' ),
						'1.1.2'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Dashboard tab.
	 */
	public function render_tab_dashboard() {
		$data           = $this->repository->read( false );
		$samples        = $data['samples'] ?? array();
		$latest_sample  = ! empty( $samples ) ? end( $samples ) : null;
		$state_data     = $this->state_store->get_state_data();

		require __DIR__ . '/views/tab-dashboard.php';
	}

	/**
	 * Render Manual Test tab.
	 */
	public function render_tab_manual_test() {
		require __DIR__ . '/views/tab-manual-test.php';
	}

	/**
	 * Render Email tab.
	 */
	public function render_tab_email() {
		$recipient = get_option( 'admin_email' );
		require __DIR__ . '/views/tab-email.php';
	}

	/**
	 * Render Logs tab.
	 */
	public function render_tab_logs() {
		$log_files     = class_exists( 'Hypercart_Logger' ) ? \Hypercart_Logger::get_log_files() : array();
		$selected_file = isset( $_GET['log_file'] ) ? sanitize_file_name( $_GET['log_file'] ) : null;
		$log_content   = '';

		// SECURITY: Only allow log files from the allowlist to prevent directory traversal.
		if ( $selected_file && ! in_array( $selected_file, $log_files, true ) ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Rejected invalid log file selection',
				array(
					'requested_file' => $selected_file,
					'user'           => wp_get_current_user()->user_login,
				)
			);
			$selected_file = null; // Reject invalid selection.
		}

		if ( $selected_file && class_exists( 'Hypercart_Logger' ) ) {
			$log_content = \Hypercart_Logger::read_log( $selected_file );
		} elseif ( ! empty( $log_files ) && class_exists( 'Hypercart_Logger' ) ) {
			// Default to today's log.
			$log_content = \Hypercart_Logger::read_log();
		}

		require __DIR__ . '/views/tab-logs.php';
	}

	/**
	 * Render Settings tab.
	 */
	public function render_tab_settings() {
		// Handle form submission.
		if ( isset( $_POST['hsm_font_settings_nonce'] ) && wp_verify_nonce( $_POST['hsm_font_settings_nonce'], 'hsm_save_font_settings' ) ) {
			$this->save_font_settings();
		}

		require __DIR__ . '/views/tab-settings.php';
	}

	/**
	 * Save font settings.
	 */
	private function save_font_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$font_settings = isset( $_POST['font_settings'] ) ? $_POST['font_settings'] : array();

		// Sanitize inputs.
		$sanitized = array(
			'timestamp_size'   => absint( $font_settings['timestamp_size'] ?? 14 ),
			'timestamp_weight' => $this->sanitize_font_weight( $font_settings['timestamp_weight'] ?? '400', '400' ),
			'timestamp_color'  => sanitize_hex_color( $font_settings['timestamp_color'] ?? '#000000' ),
			'benchmark_size'   => absint( $font_settings['benchmark_size'] ?? 14 ),
			'benchmark_weight' => $this->sanitize_font_weight( $font_settings['benchmark_weight'] ?? '400', '400' ),
			'benchmark_color'  => sanitize_hex_color( $font_settings['benchmark_color'] ?? '#000000' ),
			'health_size'      => absint( $font_settings['health_size'] ?? 12 ),
			'health_weight'    => $this->sanitize_font_weight( $font_settings['health_weight'] ?? '600', '600' ),
			'health_healthy'   => sanitize_hex_color( $font_settings['health_healthy'] ?? '#059669' ),
			'health_unhealthy' => sanitize_hex_color( $font_settings['health_unhealthy'] ?? '#dc2626' ),
		);

		// Validate ranges.
		$sanitized['timestamp_size'] = max( 8, min( 32, $sanitized['timestamp_size'] ) );
		$sanitized['benchmark_size'] = max( 8, min( 32, $sanitized['benchmark_size'] ) );
		$sanitized['health_size']    = max( 8, min( 32, $sanitized['health_size'] ) );

		// Save to database.
		update_option( 'hypercart_server_monitor_font_settings', $sanitized );

		// Log the change.
		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'Font settings updated',
			array(
				'user'     => wp_get_current_user()->user_login,
				'settings' => $sanitized,
			)
		);

		// Show success message.
		add_settings_error(
			'hypercart_server_monitor_font_settings',
			'settings_updated',
			__( 'Font settings saved successfully.', 'hypercart-server-monitor' ),
			'success'
		);
	}

		/**
		 * Sanitize a font-weight value to a strict allowlist.
		 *
		 * @since 0.4.28
		 * @param mixed  $weight   Raw font weight value.
		 * @param string $default  Default value if invalid.
		 * @return string Sanitized font weight (e.g. "400").
		 */
		private function sanitize_font_weight( $weight, $default ) {
			$allowed = array( '100', '200', '300', '400', '500', '600', '700', '800', '900' );

			$weight = is_scalar( $weight ) ? trim( (string) $weight ) : '';
			if ( in_array( $weight, $allowed, true ) ) {
				return $weight;
			}

			$weight_int = absint( $weight );
			$weight_str = (string) $weight_int;
			if ( in_array( $weight_str, $allowed, true ) ) {
				return $weight_str;
			}

			return in_array( $default, $allowed, true ) ? $default : '400';
		}

	/**
	 * Render Debug tab.
	 */
	public function render_tab_debug() {
		$state_data     = $this->state_store->get_state_data();
		$file_status    = $this->repository->get_file_status();
		$lock_status    = LockHelper::get_status();
		$cron_status    = $this->get_cron_status();
		$data_dir       = dirname( $file_status['path'] );
		$self_tests     = array(
			array(
				'label'  => __( 'Log Handling and File Visibility', 'hypercart-server-monitor' ),
				'status' => ( file_exists( $data_dir . '/.htaccess' ) && file_exists( $data_dir . '/index.html' ) ) ? 'ok' : 'fail',
				'detail' => ( file_exists( $data_dir . '/.htaccess' ) && file_exists( $data_dir . '/index.html' ) )
					? __( 'Security files (.htaccess and index.html) are present.', 'hypercart-server-monitor' )
					: sprintf(
						/* translators: %s: link to security readme */
						__( 'Note: Please review the SECURITY-README viewer for guidance. See %s.', 'hypercart-server-monitor' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=hypercart-security-guide' ) ) . '">SECURITY-README viewer</a>'
					),
			),
			array(
				'label'  => __( 'Manual test AJAX action registered', 'hypercart-server-monitor' ),
				'status' => has_action( 'wp_ajax_hsm_run_manual_test' ) ? 'ok' : 'fail',
				'detail' => has_action( 'wp_ajax_hsm_run_manual_test' ) ? __( 'Registered', 'hypercart-server-monitor' ) : __( 'Missing', 'hypercart-server-monitor' ),
			),
			array(
				'label'  => __( 'Breaker probe methods available', 'hypercart-server-monitor' ),
				'status' => method_exists( $this->state_store, 'record_probe_success' ) && method_exists( $this->state_store, 'record_probe_failure' ) ? 'ok' : 'fail',
				'detail' => method_exists( $this->state_store, 'record_probe_success' ) && method_exists( $this->state_store, 'record_probe_failure' )
					? __( 'Available', 'hypercart-server-monitor' )
					: __( 'Missing', 'hypercart-server-monitor' ),
			),
			array(
				'label'  => __( 'Breaker cooldown field present', 'hypercart-server-monitor' ),
				'status' => array_key_exists( 'cooldown_until', $state_data ) ? 'ok' : 'fail',
				'detail' => array_key_exists( 'cooldown_until', $state_data ) ? __( 'Present', 'hypercart-server-monitor' ) : __( 'Missing', 'hypercart-server-monitor' ),
			),
			array(
				'label'  => __( 'Breaker half-open state available', 'hypercart-server-monitor' ),
				'status' => method_exists( $this->state_store, 'begin_run' ) ? 'ok' : 'fail',
				'detail' => method_exists( $this->state_store, 'begin_run' ) ? __( 'Available', 'hypercart-server-monitor' ) : __( 'Missing', 'hypercart-server-monitor' ),
			),
			array(
				'label'  => __( 'Manual test runner available', 'hypercart-server-monitor' ),
				'status' => method_exists( '\Hypercart_Server_Monitor\Plugin', 'run_manual_test' ) ? 'ok' : 'fail',
				'detail' => method_exists( '\Hypercart_Server_Monitor\Plugin', 'run_manual_test' ) ? __( 'Available', 'hypercart-server-monitor' ) : __( 'Missing', 'hypercart-server-monitor' ),
			),
			array(
				'label'  => __( 'Manual test view present', 'hypercart-server-monitor' ),
				'status' => file_exists( __DIR__ . '/views/tab-manual-test.php' ) ? 'ok' : 'fail',
				'detail' => file_exists( __DIR__ . '/views/tab-manual-test.php' ) ? __( 'Found', 'hypercart-server-monitor' ) : __( 'Missing', 'hypercart-server-monitor' ),
			),
		);

		require __DIR__ . '/views/tab-debug.php';
	}

	/**
	 * Get cron status for debugging.
	 *
	 * @return array Cron status information.
	 */
	private function get_cron_status() {
		$next_run = wp_next_scheduled( SchedulerService::CRON_HOOK );

		return array(
			'scheduled'      => (bool) $next_run,
			'next_run_utc'   => $next_run ? $next_run : null,
			'next_run_local' => $next_run ? \Hypercart_Time::format( 'Y-m-d H:i:s', $next_run ) : null,
			'hook'           => SchedulerService::CRON_HOOK,
			'interval'       => SchedulerService::SCHEDULE_INTERVAL,
		);
	}

	/**
	 * AJAX handler for manual test.
	 */
	public function ajax_run_manual_test() {
		// Verify nonce.
		check_ajax_referer( 'hsm_manual_test', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'hypercart-server-monitor' ) ) );
		}

		// Run manual monitoring through the shared FSM path.
		try {
			// Safeguard: do not bypass the FSM breaker flow from admin.
			$result = \Hypercart_Server_Monitor\Plugin::get_instance()->run_manual_test();
			if ( empty( $result['success'] ) ) {
				throw new \Exception( $result['message'] ?? 'Manual test failed. Check logs for details.' );
			}

			$raw_metrics = $result['raw_metrics'] ?? array();
			$score       = $result['score'] ?? array();
			$duration_ms = $result['duration_ms'] ?? 0;

			// Check if benchmark is supported.
			$warnings = array();
			if ( isset( $raw_metrics['benchmark']['supported'] ) && ! $raw_metrics['benchmark']['supported'] ) {
				$warnings[] = __( 'Benchmark not supported on this system', 'hypercart-server-monitor' );
			}

			// Get current cron health status for display.
			$plugin             = \Hypercart_Server_Monitor\Plugin::get_instance();
			$cron_health_state  = $plugin->determine_cron_health_state();
			$cron_health_status = array(
				'status'            => $cron_health_state['status'],
				'last_run_utc'      => $cron_health_state['last_run'] ? gmdate( 'c', $cron_health_state['last_run'] ) : null,
				'next_run_utc'      => $cron_health_state['next_run'] ? gmdate( 'c', $cron_health_state['next_run'] ) : null,
				'transient_present' => (bool) $cron_health_state['transient_present'],
			);

			wp_send_json_success(
				array(
					'score'       => $score,
					'raw_metrics' => $raw_metrics,
					'duration_ms' => $duration_ms,
					'timestamp'   => $result['timestamp'] ?? \Hypercart_Time::format( 'Y-m-d H:i:s' ),
					'warnings'    => $warnings,
					'logged'      => true,
					'cron_health' => $cron_health_status,
				)
			);
		} catch ( \Exception $e ) {
			// Log error.
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Manual test failed',
				array( 'error' => $e->getMessage() )
			);

			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'logged'  => false,
				)
			);
		}
	}

	/**
	 * Handle AJAX request to send test email.
	 */
	public function ajax_send_test_email() {
		// Verify nonce.
		check_ajax_referer( 'hsm_send_test_email', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		try {
			// Get most recent sample from JSON.
			$data = $this->repository->read( false );

			// Debug logging.
			\Hypercart_Logger::debug(
				'hypercart-server-monitor',
				'Test email - repository data',
				array(
					'data_keys'     => array_keys( $data ),
					'has_samples'   => isset( $data['samples'] ),
					'samples_count' => isset( $data['samples'] ) ? count( $data['samples'] ) : 0,
					'samples_type'  => isset( $data['samples'] ) ? gettype( $data['samples'] ) : 'not set',
				)
			);

			$samples = $data['samples'] ?? array();

			if ( empty( $samples ) ) {
				throw new \Exception(
					'No benchmark data available. The JSON file is empty. ' .
					'Run a manual test or wait for the next scheduled cron run (every 15 minutes), ' .
					'or check the Debug tab to see if cron is running properly.'
				);
			}

			// Get latest sample (reset array pointer first).
			$latest_sample = end( $samples );

			// Log test email attempt.
			\Hypercart_Logger::info(
				'hypercart-server-monitor',
				'Test email requested from admin UI',
				array( 'user' => wp_get_current_user()->user_login )
			);

			// Send email.
			$email_service = new \Hypercart_Server_Monitor\Services\EmailService();
			$sent          = $email_service->send_notification( $latest_sample );

			if ( ! $sent ) {
				throw new \Exception( 'Failed to send email. Check logs for details.' );
			}

			wp_send_json_success(
				array(
					'message'   => 'Test email sent successfully!',
					'recipient' => get_option( 'admin_email' ),
					'score'     => $latest_sample['score']['combined'] ?? 0,
					'timestamp' => $latest_sample['ts_utc'] ?? '',
				)
			);
		} catch ( \Exception $e ) {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Test email failed',
				array( 'error' => $e->getMessage() )
			);

			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle AJAX request to toggle email notifications.
	 */
	public function ajax_toggle_email_notifications() {
		// Verify nonce.
		check_ajax_referer( 'hsm_toggle_email_notifications', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Get and validate enabled state.
		$enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'] ? '1' : '0';

		// Save option.
		update_option( \Hypercart_Server_Monitor\Plugin::OPTION_EMAIL_NOTIFICATIONS_ENABLED, $enabled );

		// Log the change.
		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'Email notifications toggled',
			array(
				'enabled' => $enabled,
				'user'    => wp_get_current_user()->user_login,
			)
		);

		wp_send_json_success(
			array(
				'message' => $enabled === '1'
					? __( 'Email notifications enabled', 'hypercart-server-monitor' )
					: __( 'Email notifications disabled', 'hypercart-server-monitor' ),
				'enabled' => $enabled,
			)
		);
	}

	/**
	 * AJAX handler for breaker self test.
	 */
	public function ajax_run_breaker_self_test() {
		// Verify nonce.
		check_ajax_referer( 'hsm_debug', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$result = $this->state_store->run_breaker_self_test();
		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => $result['message'] ?? 'Breaker self test failed.',
					'steps'   => $result['steps'] ?? array(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => $result['message'] ?? 'Breaker self test completed.',
				'steps'   => $result['steps'] ?? array(),
			)
		);
	}

	/**
	 * Handle AJAX request to manually schedule cron.
	 */
	public function ajax_schedule_cron() {
		// Verify nonce.
		check_ajax_referer( 'hsm_manual_test', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		try {
			$scheduler = new \Hypercart_Server_Monitor\Services\SchedulerService();
			$next_run  = $scheduler->get_next_run();
			if ( $scheduler->is_scheduled() ) {
				wp_send_json_error(
					array(
						'message'  => 'Cron is already scheduled',
						'next_run' => $next_run ? \Hypercart_Time::format( 'Y-m-d H:i:s', $next_run ) : null,
					)
				);
			}

			// Schedule cron.
			$scheduler->schedule();

			// Verify it was scheduled.
			$next_run = $scheduler->get_next_run();
			if ( ! $scheduler->is_scheduled() || ! $next_run ) {
				throw new \Exception( 'Failed to schedule cron. Check logs for details.' );
			}

			\Hypercart_Logger::info(
				'hypercart-server-monitor',
				'Cron manually scheduled from admin UI',
				array(
					'user'     => wp_get_current_user()->user_login,
					'next_run' => $next_run,
				)
			);

			wp_send_json_success(
				array(
					'message'  => 'Cron scheduled successfully!',
					'next_run' => \Hypercart_Time::format( 'Y-m-d H:i:s', $next_run ),
				)
			);
		} catch ( \Exception $e ) {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Failed to schedule cron from admin UI',
				array( 'error' => $e->getMessage() )
			);

			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

}
