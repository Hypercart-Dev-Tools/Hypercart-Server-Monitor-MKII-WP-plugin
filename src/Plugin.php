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
		$this->scheduler = new Services\SchedulerService( $this->state_store );

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

		// Add settings link on plugins page.
		add_filter(
			'plugin_action_links_' . HYPERCART_SERVER_MONITOR_PLUGIN_BASENAME,
			array( $this, 'add_settings_link' )
		);

		// Admin menu.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Run monitoring task (called by cron).
	 */
	public function run_monitoring() {
		$start_time = \Hypercart_Time::now();

		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'Monitoring task triggered',
			array(
				'time' => \Hypercart_Time::iso8601( $start_time ),
			)
		);

		// Check if circuit breaker is tripped.
		$state_data = $this->state_store->get_state_data();
		if ( 'tripped' === $state_data['state'] ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Circuit breaker tripped, skipping run',
				array( 'failure_count' => $state_data['failure_count'] )
			);
			return;
		}

		// Try to acquire lock.
		if ( ! Helpers\LockHelper::acquire() ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Failed to acquire lock, skipping run',
				array()
			);
			return;
		}

		try {
			// Transition to scheduled state.
			$this->state_store->transition_to( 'scheduled' );

			// Transition to running state.
			$this->state_store->transition_to( 'running' );

			// Collect metrics.
			$raw_metrics = $this->collect_metrics();

			// Calculate score.
			$scorer = new Services\ScoringService();
			$score  = $scorer->calculate_score( $raw_metrics );

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
				),
			);

			if ( ! $repository->add_sample( $sample ) ) {
				throw new \Exception( 'Failed to write JSON' );
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
			$this->state_store->reset_failures();

			\Hypercart_Logger::info(
				'hypercart-server-monitor',
				'Monitoring task completed successfully',
				array(
					'score'       => $score,
					'duration_ms' => $duration_ms,
				)
			);

		} catch ( \Exception $e ) {
			// Record error.
			$this->state_store->record_error( $e->getMessage() );
			$this->state_store->transition_to( 'error' );

			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Monitoring task failed',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);
		} finally {
			// Always release lock.
			Helpers\LockHelper::release();
		}
	}

	/**
	 * Collect all metrics.
	 *
	 * @return array Raw metrics.
	 */
	private function collect_metrics() {
		$collectors = array(
			new Metrics\CpuLoadCollector(),
			new Metrics\MemoryCollector(),
			new Metrics\DiskCollector(),
		);

		$metrics = array();

		foreach ( $collectors as $collector ) {
			$name           = $collector->get_name();
			$metrics[ $name ] = $collector->collect();
		}

		return $metrics;
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
			esc_url( admin_url( 'tools.php?page=hypercart-server-monitor' ) ),
			esc_html__( 'Settings', 'hypercart-server-monitor' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		add_management_page(
			__( 'Hypercart Server Monitor', 'hypercart-server-monitor' ),
			__( 'Server Monitor', 'hypercart-server-monitor' ),
			'manage_options',
			'hypercart-server-monitor',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page (placeholder for Phase 4).
	 */
	public function render_admin_page() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Hypercart Server Monitor', 'hypercart-server-monitor' ) . '</h1>';
		echo '<p>' . esc_html__( 'Admin UI coming in Phase 4...', 'hypercart-server-monitor' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		// Initialize state store.
		$state_store = new Domain\FsmStateStore();
		$state_store->initialize();

		// Schedule cron event.
		$scheduler = new Services\SchedulerService( $state_store );
		$scheduler->schedule();

		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'Plugin activation complete',
			array(
				'cron_scheduled' => wp_next_scheduled( 'hypercart_server_monitor_run' ) !== false,
			)
		);
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		// Unschedule cron event.
		$timestamp = wp_next_scheduled( 'hypercart_server_monitor_run' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'hypercart_server_monitor_run' );
		}

		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'Plugin deactivation complete',
			array(
				'cron_unscheduled' => wp_next_scheduled( 'hypercart_server_monitor_run' ) === false,
			)
		);
	}
}

