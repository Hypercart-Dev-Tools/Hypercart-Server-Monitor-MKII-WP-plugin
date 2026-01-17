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
		add_action( 'wp_ajax_hsm_schedule_cron', array( $this, 'ajax_schedule_cron' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Server Monitor', 'hypercart-server-monitor' ),
			__( 'Server Monitor', 'hypercart-server-monitor' ),
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

		// Enqueue custom admin styles.
		wp_enqueue_style(
			'hypercart-server-monitor-admin',
			plugins_url( 'assets/admin.css', HYPERCART_SERVER_MONITOR_PLUGIN_FILE ),
			array(),
			HYPERCART_SERVER_MONITOR_VERSION
		);

		// Enqueue custom admin scripts.
		wp_enqueue_script(
			'hypercart-server-monitor-admin',
			plugins_url( 'assets/admin.js', HYPERCART_SERVER_MONITOR_PLUGIN_FILE ),
			array( 'jquery' ),
			HYPERCART_SERVER_MONITOR_VERSION,
			true
		);

		// Localize script for AJAX.
		wp_localize_script(
			'hypercart-server-monitor-admin',
			'hsmAdmin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'hsm_manual_test' ),
				'emailNonce' => wp_create_nonce( 'hsm_send_test_email' ),
			)
		);
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
		echo '<h1>' . esc_html__( 'Server Monitor', 'hypercart-server-monitor' ) . '</h1>';

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
			<h1><?php esc_html_e( 'Server Monitor', 'hypercart-server-monitor' ); ?></h1>
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
	 * Render Debug tab.
	 */
	public function render_tab_debug() {
		$state_data     = $this->state_store->get_state_data();
		$file_status    = $this->repository->get_file_status();
		$lock_status    = LockHelper::get_status();
		$cron_status    = $this->get_cron_status();

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

		// Run monitoring without logging to JSON.
		try {
			$start_time = \Hypercart_Time::now();

			// Log manual test start.
			\Hypercart_Logger::info(
				'hypercart-server-monitor',
				'Manual test started from admin UI',
				array( 'user' => wp_get_current_user()->user_login )
			);

			// Collect metrics.
			$raw_metrics = $this->collect_metrics();

			// Log collected metrics for debugging.
			\Hypercart_Logger::debug(
				'hypercart-server-monitor',
				'Manual test metrics collected',
				array( 'raw_metrics' => $raw_metrics )
			);

			// Calculate score (detailed format).
			$scorer = new \Hypercart_Server_Monitor\Services\ScoringService();
			$score  = $scorer->calculate_score( $raw_metrics, true );

			// Validate score.
			if ( ! is_array( $score ) || ! isset( $score['combined'] ) ) {
				\Hypercart_Logger::error(
					'hypercart-server-monitor',
					'Scoring service returned invalid data',
					array(
						'score_type'  => gettype( $score ),
						'score_value' => $score,
					)
				);
				throw new \Exception( 'Scoring service returned invalid data. Check logs for details.' );
			}

			$duration_ms = ( \Hypercart_Time::now() - $start_time ) * 1000;

			// Log manual test completion.
			\Hypercart_Logger::info(
				'hypercart-server-monitor',
				'Manual test completed',
				array(
					'score'       => $score['combined'],
					'duration_ms' => $duration_ms,
				)
			);

			// Check if benchmark is supported.
			$warnings = array();
			if ( isset( $raw_metrics['benchmark']['supported'] ) && ! $raw_metrics['benchmark']['supported'] ) {
				$warnings[] = __( 'Benchmark not supported on this system', 'hypercart-server-monitor' );
			}

			wp_send_json_success(
				array(
					'score'       => $score,
					'raw_metrics' => $raw_metrics,
					'duration_ms' => $duration_ms,
					'timestamp'   => \Hypercart_Time::format( 'Y-m-d H:i:s' ),
					'warnings'    => $warnings,
					'logged'      => true,
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
					'logged'  => true,
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
					'Manual tests do NOT save to JSON (by design). ' .
					'Please wait for the next scheduled cron run (every 15 minutes), ' .
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

	/**
	 * Collect benchmark metrics.
	 *
	 * @return array Benchmark metrics.
	 */
	private function collect_metrics() {
		$metrics_service = new \Hypercart_Server_Monitor\Services\MetricsService();
		return $metrics_service->collect();
	}
}
