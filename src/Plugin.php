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
	 * Option key for last cron run timestamp.
	 */
	const OPTION_LAST_CRON_RUN = 'hypercart_server_monitor_last_cron_run';

	/**
	 * Transient key for cron health check.
	 */
	const TRANSIENT_CRON_HEALTH = 'hypercart_server_monitor_cron_health_check';

	/**
	 * Option key for email notifications enabled/disabled.
	 */
	const OPTION_EMAIL_NOTIFICATIONS_ENABLED = 'hypercart_server_monitor_email_notifications_enabled';

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

		// Register cron health REST endpoint.
		add_action( 'rest_api_init', array( $this, 'register_cron_health_endpoint' ) );
	}

	/**
	 * Register cron health REST endpoint.
	 */
	public function register_cron_health_endpoint() {
		register_rest_route(
			'cron-health/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cron_health_status' ),
				'permission_callback' => '__return_true',
			)
		);
	}

		/**
		 * Get cron health status.
		 *
		 * Applies a simple per-IP rate limit to the public cron health endpoint
		 * before returning the current health status.
		 *
		 * @return \WP_REST_Response
		 */
		public function get_cron_health_status() {
			// Per-IP rate limiting for the public cron health endpoint.
			$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
			$ip          = is_string( $remote_addr ) ? trim( $remote_addr ) : 'unknown';

			$window_seconds = (int) apply_filters( 'hypercart_server_monitor_cron_health_window_seconds', 300 );
			if ( $window_seconds <= 0 ) {
				$window_seconds = 300;
			}

			$max_requests = (int) apply_filters( 'hypercart_server_monitor_cron_health_rate_limit', 6 );
			if ( $max_requests <= 0 ) {
				$max_requests = 6;
			}

			$key = 'hypercart_server_monitor_cron_health_rl_' . md5( $ip );
			$now = time();

			$data = get_transient( $key );

			if ( ! is_array( $data ) || ! isset( $data['expires_at'] ) || $data['expires_at'] <= $now ) {
				$data = array(
					'count'      => 1,
					'expires_at' => $now + $window_seconds,
				);
			} elseif ( isset( $data['count'] ) && $data['count'] >= $max_requests ) {
				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'Cron health endpoint rate limited',
					array(
						'ip'            => $ip,
						'window'        => $window_seconds,
						'max_requests'  => $max_requests,
						'current_count' => (int) $data['count'],
					)
				);

				$response = array(
					'status'  => 'rate_limited',
					'message' => __( 'Rate limit exceeded, try again later.', 'hypercart-server-monitor' ),
				);

				return new \WP_REST_Response( $response, 429 );
			} else {
				$data['count'] = isset( $data['count'] ) ? ( (int) $data['count'] + 1 ) : 1;
			}

			// Store updated rate limit window for this IP.
			set_transient( $key, $data, $window_seconds );

			$health_state = $this->determine_cron_health_state();
			$last_run     = $health_state['last_run'];
			$status       = $health_state['status'];

			$response = array(
				'status'   => $status,
				'last_run' => $last_run ? gmdate( 'c', $last_run ) : null,
			);

			return new \WP_REST_Response( $response, 200 );
		}

	/**
	 * Determine cron health state without rate limiting.
	 *
	 * This mirrors the public REST endpoint's health calculation but can be
	 * reused internally (e.g., when persisting per-sample health snapshots).
	 *
	 * @param int|null $override_last_run Optional last run timestamp override.
	 * @return array{status:string,last_run:?int,next_run:?int,transient_present:bool}
	 */
	public function determine_cron_health_state( $override_last_run = null ) {
		$last_run = null;
		if ( is_numeric( $override_last_run ) ) {
			$last_run = (int) $override_last_run;
		} else {
			$last_run = get_option( self::OPTION_LAST_CRON_RUN );
			$last_run = $last_run ? (int) $last_run : null;
		}

		$next_run = wp_next_scheduled( Services\SchedulerService::CRON_HOOK );
		$next_run = $next_run ? (int) $next_run : null;

		$transient_present = ( false !== get_transient( self::TRANSIENT_CRON_HEALTH ) );

		$status = ( $last_run && $next_run && $transient_present ) ? 'healthy' : 'unhealthy';

		return array(
			'status'           => $status,
			'last_run'         => $last_run,
			'next_run'         => $next_run,
			'transient_present' => $transient_present,
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

		// Enqueue shared styles (used by both admin and frontend).
		wp_enqueue_style(
			'hypercart-server-monitor-shared',
			plugins_url( 'assets/shared.css', HYPERCART_SERVER_MONITOR_PLUGIN_FILE ),
			array(),
			HYPERCART_SERVER_MONITOR_VERSION
		);

		// Enqueue frontend styles (depends on shared).
		wp_enqueue_style(
			'hypercart-server-monitor-frontend',
			plugins_url( 'assets/frontend.css', HYPERCART_SERVER_MONITOR_PLUGIN_FILE ),
			array( 'hypercart-server-monitor-shared' ),
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
		// Check if email notifications are enabled (default: true).
		$email_enabled = get_option( self::OPTION_EMAIL_NOTIFICATIONS_ENABLED, '1' );
		$send_email    = '1' === $email_enabled;

		// Safeguard: keep breaker gating centralized via the FSM store.
		$this->run_monitoring_internal(
			array(
				'send_email'             => $send_email,
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

		// Update cron health check indicators if this is a scheduled run.
		if ( ! empty( $options['use_scheduled_state'] ) ) {
			update_option( self::OPTION_LAST_CRON_RUN, $start_time );
			set_transient( self::TRANSIENT_CRON_HEALTH, $start_time, HOUR_IN_SECONDS );
		}

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
				if ( ! $this->state_store->transition_to( 'scheduled' ) ) {
					\Hypercart_Logger::warning(
						'hypercart-server-monitor',
						'Failed to transition to scheduled state (lock not acquired)',
						array()
					);
				}
			}

			// Transition to running state.
			if ( ! $this->state_store->transition_to( 'running' ) ) {
				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'Failed to transition to running state (lock not acquired)',
					array()
				);
			}

			// Collect metrics.
			$raw_metrics = $this->collect_metrics();

			// Calculate score (detailed format for storage).
			$scorer = new Services\ScoringService();
			$score  = $scorer->calculate_score( $raw_metrics, true );

			// Transition to writing state.
			if ( ! $this->state_store->transition_to( 'writing' ) ) {
				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'Failed to transition to writing state (lock not acquired)',
					array()
				);
			}

			// Snapshot cron health at the end of this run for historical display.
			$override_last_run   = ! empty( $options['use_scheduled_state'] ) ? $start_time : null;
			$cron_health_state   = $this->determine_cron_health_state( $override_last_run );
			$cron_health_snapshot = array(
				'status'            => $cron_health_state['status'],
				'last_run_utc'      => $cron_health_state['last_run'] ? gmdate( 'c', $cron_health_state['last_run'] ) : null,
				'next_run_utc'      => $cron_health_state['next_run'] ? gmdate( 'c', $cron_health_state['next_run'] ) : null,
				'transient_present' => (bool) $cron_health_state['transient_present'],
			);

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
					'cron_health' => $cron_health_snapshot,
				),
			);

			if ( ! $repository->add_sample( $sample ) ) {
				throw new \Exception( 'Failed to write JSON' );
			}

			if ( $options['send_email'] ) {
				// Transition to emailing state.
				if ( ! $this->state_store->transition_to( 'emailing' ) ) {
					\Hypercart_Logger::warning(
						'hypercart-server-monitor',
						'Failed to transition to emailing state (lock not acquired)',
						array()
					);
				}

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
			if ( ! $this->state_store->transition_to(
				'completed',
				array(
					'last_run_utc'     => $start_time,
					'last_duration_ms' => $duration_ms,
				)
			) ) {
				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'Failed to transition to completed state (lock not acquired)',
					array()
				);
			}

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
				$probe_result = $this->state_store->record_probe_failure( $e->getMessage() );
				// If lock failed, fall back to error state transition.
				if ( false === $probe_result ) {
					\Hypercart_Logger::warning(
						'hypercart-server-monitor',
						'Failed to record probe failure (lock not acquired), falling back to error state',
						array()
					);
					if ( ! $this->state_store->transition_to( 'error' ) ) {
						\Hypercart_Logger::warning(
							'hypercart-server-monitor',
							'Failed to transition to error state (lock not acquired)',
							array()
						);
					}
				}
			} elseif ( $options['track_failures'] ) {
				$error_result = $this->state_store->record_error( $e->getMessage() );
				if ( ! is_array( $error_result ) || empty( $error_result['tripped'] ) ) {
					if ( ! $this->state_store->transition_to( 'error' ) ) {
						\Hypercart_Logger::warning(
							'hypercart-server-monitor',
							'Failed to transition to error state (lock not acquired)',
							array()
						);
					}
				}
			} else {
				if ( ! $this->state_store->transition_to( 'error' ) ) {
					\Hypercart_Logger::warning(
						'hypercart-server-monitor',
						'Failed to transition to error state (lock not acquired)',
						array()
					);
				}
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
