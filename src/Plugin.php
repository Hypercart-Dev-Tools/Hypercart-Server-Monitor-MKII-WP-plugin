<?php
/**
 * Main Plugin Class
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor;

/**
 * Main plugin class - singleton pattern.
 */
class Plugin {
	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Scheduler service.
	 *
	 * @var Services\SchedulerService|null
	 */
	private $scheduler;

	/**
	 * FSM state store.
	 *
	 * @var Domain\FsmStateStore|null
	 */
	private $state_store;

	/**
	 * Admin controller.
	 *
	 * @var Admin\AdminController|null
	 */
	private $admin_controller;

	/**
	 * Get plugin instance (singleton).
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (singleton pattern).
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin.
	 */
	private function init() {
		// Initialize state store.
		$this->state_store = new Domain\FsmStateStore();

		// Initialize scheduler.
		$this->scheduler = new Services\SchedulerService();

		// Initialize admin controller (if in admin).
		if ( is_admin() ) {
			$repository             = new Persistence\HealthRepository();
			$this->admin_controller = new Admin\AdminController( $this->state_store, $repository );
			$this->admin_controller->init();
		}

		// Register hooks.
		$this->register_hooks();

		// Log initialization.
		\Hypercart_Logger::debug(
			'hypercart-server-monitor',
			'Plugin initialized',
			array(
				'state' => $this->state_store->get_current_state(),
			)
		);
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks() {
		// Register custom cron schedule.
		add_filter( 'cron_schedules', array( $this->scheduler, 'add_custom_schedule' ) );

		// Register cron hook.
		add_action( 'hypercart_server_monitor_run', array( $this, 'run_monitoring' ) );

		// Register read-only dashboard shortcode.
		add_shortcode( 'hypercart_server_monitor_dashboard', array( $this, 'render_readonly_dashboard_shortcode' ) );

		// Detect shortcode early and add noindex meta tag if needed.
		add_action( 'wp', array( $this, 'detect_shortcode_and_add_noindex' ) );

		// Add settings link on plugins page.
		add_filter(
			'plugin_action_links_' . HYPERCART_SERVER_MONITOR_PLUGIN_BASENAME,
			array( $this, 'add_settings_link' )
		);
	}

	/**
	 * Detect shortcode in post content and add noindex meta tag if needed.
	 *
	 * This runs during the 'wp' action, which fires after the query is set up
	 * but before wp_head, allowing us to add the noindex meta tag in time.
	 */
	public function detect_shortcode_and_add_noindex() {
		global $post;

		// Only run on singular posts/pages.
		if ( ! is_singular() || ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		// Check if the shortcode exists in the post content.
		if ( ! has_shortcode( $post->post_content, 'hypercart_server_monitor_dashboard' ) ) {
			return;
		}

		// Parse shortcode attributes to check noindex setting.
		$pattern = get_shortcode_regex( array( 'hypercart_server_monitor_dashboard' ) );
		if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches ) ) {
			foreach ( $matches[0] as $index => $shortcode_match ) {
				// Extract attributes from the shortcode.
				$atts = shortcode_parse_atts( $matches[3][ $index ] );
				if ( ! is_array( $atts ) ) {
					$atts = array();
				}

				// Check noindex attribute (default to 'true').
				$noindex = isset( $atts['noindex'] ) ? $atts['noindex'] : 'true';
				$is_noindex_enabled = wp_validate_boolean( $noindex );

				if ( $is_noindex_enabled ) {
					// Add noindex meta tag via wp_head action.
					add_action(
						'wp_head',
						function () {
							echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
						},
						1
					);
					// Only process the first shortcode instance.
					break;
				}
			}
		}
	}

	/**
	 * Render the read-only dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function render_readonly_dashboard_shortcode( $atts ) {
		// START V2 NO-INDEX RULE - DO NOT REFACTOR
		// Note: Noindex meta tag is added earlier via detect_shortcode_and_add_noindex()
		// which runs during 'wp' action (before wp_head). This ensures proper timing.
		$atts = shortcode_atts(
			array(
				'noindex' => 'true', // Default to true for safety.
			),
			$atts,
			'hypercart_server_monitor_dashboard'
		);

		$is_noindex_enabled = wp_validate_boolean( $atts['noindex'] );
		// END V2 NO-INDEX RULE

		// Safeguard: this view must remain read-only (no writes or state changes).
		$repository    = new Persistence\HealthRepository();
		$data          = $repository->read( false );
		$samples       = $data['samples'] ?? array();
		$latest_sample = ! empty( $samples ) ? end( $samples ) : null;

		if ( class_exists( 'Hypercart_Charts' ) ) {
			\Hypercart_Charts::register_assets();
			\Hypercart_Charts::enqueue( array( 'context' => 'hypercart-server-monitor-frontend' ) );
		}

		wp_enqueue_style(
			'hypercart-server-monitor-frontend',
			plugins_url( 'assets/admin.css', HYPERCART_SERVER_MONITOR_PLUGIN_FILE ),
			array(),
			HYPERCART_SERVER_MONITOR_VERSION
		);

		ob_start();
		include __DIR__ . '/Frontend/views/shortcode-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Run monitoring task (called by cron).
	 */
	public function run_monitoring() {
		// Safeguard: keep breaker gating centralized via the FSM store.
		$this->run_monitoring_internal(
			array(
				'send_email'             => true,
				'respect_circuit_breaker'=> true,
				'track_failures'         => true,
				'allow_probe'            => true,
				'use_scheduled_state'    => true,
				'source'                 => 'scheduled',
				'context_label'          => 'Monitoring task',
			)
		);
	}

	/**
	 * Run manual monitoring test (called by admin UI).
	 *
	 * @return array Result payload for UI.
	 */
	public function run_manual_test() {
		// Safeguard: manual tests must use the same breaker path as scheduled runs.
		return $this->run_monitoring_internal(
			array(
				'send_email'             => false,
				'respect_circuit_breaker'=> true,
				'track_failures'         => true,
				'allow_probe'            => true,
				'use_scheduled_state'    => false,
				'source'                 => 'manual',
				'context_label'          => 'Manual test',
			)
		);
	}

	/**
	 * Internal monitoring execution path.
	 *
	 * Note: This sequence is the single authoritative run path; avoid refactors
	 * unless the FSM breaker flow and manual test integration are updated together.
	 *
	 * @param array $options Run options.
	 * @return array Result payload for UI or diagnostics.
	 */
	private function run_monitoring_internal( $options ) {
		$options = wp_parse_args(
			$options,
			array(
				'send_email'              => true,
				'respect_circuit_breaker' => true,
				'track_failures'          => true,
				'allow_probe'             => true,
				'use_scheduled_state'     => true,
				'source'                  => 'scheduled',
				'context_label'           => 'Monitoring task',
			)
		);

		$start_time = \Hypercart_Time::now();

		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			$options['context_label'] . ' triggered',
			array(
				'time' => \Hypercart_Time::iso8601( $start_time ),
			)
		);

		// Try to acquire lock.
		if ( ! Helpers\LockHelper::acquire() ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Failed to acquire lock, skipping run',
				array()
			);
			return array(
				'success' => false,
				'message' => __( 'Another run is already in progress.', 'hypercart-server-monitor' ),
			);
		}

		try {
			$probe_run = false;
			if ( $options['respect_circuit_breaker'] ) {
				$breaker_status = $this->state_store->begin_run( $options['allow_probe'] );
				if ( ! is_array( $breaker_status ) || empty( $breaker_status['allowed'] ) ) {
					return array(
						'success' => false,
						'message' => $breaker_status['message'] ?? __( 'Run blocked by circuit breaker.', 'hypercart-server-monitor' ),
					);
				}

				$probe_run = ! empty( $breaker_status['probe'] );
			}

			// Transition to scheduled state (cron only).
			if ( $options['use_scheduled_state'] ) {
				$this->state_store->transition_to( 'scheduled' );
			}

			// Transition to running state.
			$this->state_store->transition_to( 'running' );

			// Collect metrics.
			$raw_metrics = $this->collect_metrics();

			// Calculate score (detailed format for storage).
			$scorer = new Services\ScoringService();
			$score  = $scorer->calculate_score( $raw_metrics, true );

			// Transition to writing state.
			$this->state_store->transition_to( 'writing' );

			// Persist to JSON.
			$repository = new Persistence\HealthRepository();
			$sample     = array(
				'ts_utc' => \Hypercart_Time::iso8601( $start_time ),
				'score'  => $score,
				'raw'    => $raw_metrics,
				'meta'   => array(
					'collector'   => 'hypercart-server-monitor',
					'duration_ms' => ( \Hypercart_Time::now() - $start_time ) * 1000,
					'source'      => $options['source'],
				),
			);

			if ( ! $repository->add_sample( $sample ) ) {
				throw new \Exception( 'Failed to write JSON' );
			}

			if ( $options['send_email'] ) {
				// Transition to emailing state.
				$this->state_store->transition_to( 'emailing' );

				// Send email notification.
				$email_service = new Services\EmailService();
				$email_sent    = $email_service->send_notification( $sample );

				if ( ! $email_sent ) {
					\Hypercart_Logger::warning(
						'hypercart-server-monitor',
						'Email notification failed, but continuing',
						array()
					);
				}
			}

			// Transition to completed state.
			$duration_ms = ( \Hypercart_Time::now() - $start_time ) * 1000;
			$this->state_store->transition_to(
				'completed',
				array(
					'last_run_utc'     => $start_time,
					'last_duration_ms' => $duration_ms,
				)
			);

			// Reset failure counter on success.
			if ( $probe_run ) {
				$this->state_store->record_probe_success();
			} elseif ( $options['track_failures'] ) {
				$this->state_store->reset_failures();
			}

			\Hypercart_Logger::info(
				'hypercart-server-monitor',
				$options['context_label'] . ' completed successfully',
				array(
					'score'          => $score['combined'],
					'score_label'    => $score['label'],
					'benchmark_ms'   => $score['benchmark_ms'] ?? null,
					'duration_ms'    => $duration_ms,
				)
			);

			return array(
				'success'     => true,
				'score'       => $score,
				'raw_metrics' => $raw_metrics,
				'duration_ms' => $duration_ms,
				'timestamp'   => \Hypercart_Time::format( 'Y-m-d H:i:s', $start_time ),
				'sample'      => $sample,
			);
		} catch ( \Exception $e ) {
			// Record error.
			if ( $probe_run ) {
				$this->state_store->record_probe_failure( $e->getMessage() );
			} elseif ( $options['track_failures'] ) {
				$error_result = $this->state_store->record_error( $e->getMessage() );
				if ( ! is_array( $error_result ) || empty( $error_result['tripped'] ) ) {
					$this->state_store->transition_to( 'error' );
				}
			} else {
				$this->state_store->transition_to( 'error' );
			}

			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				$options['context_label'] . ' failed',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		} finally {
			// Always release lock.
			Helpers\LockHelper::release();
		}
	}

	/**
	 * Collect benchmark metrics.
	 *
	 * @return array Benchmark metrics.
	 */
	private function collect_metrics() {
		$metrics_service = new Services\MetricsService();
		return $metrics_service->collect();
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=hypercart-server-monitor' ) ),
			esc_html__( 'Dashboard', 'hypercart-server-monitor' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		// Initialize state store.
		$state_store = new Domain\FsmStateStore();
		$state_store->initialize();

		// Schedule cron event.
		$scheduler = new Services\SchedulerService();
		$scheduler->schedule();

		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'Plugin activation complete',
			array(
				'cron_scheduled' => $scheduler->is_scheduled(),
			)
		);
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		// Unschedule cron event.
		$scheduler = new Services\SchedulerService();
		$scheduler->unschedule();

		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'Plugin deactivation complete',
			array(
				'cron_unscheduled' => ! $scheduler->is_scheduled(),
			)
		);
	}
}
