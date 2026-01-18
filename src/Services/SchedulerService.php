<?php
/**
 * Scheduler Service
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Services;

/**
 * Manages WP-Cron scheduling for monitoring tasks.
 */
class SchedulerService {
	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'hypercart_server_monitor_run';

	/**
	 * Schedule interval name.
	 */
	const SCHEDULE_INTERVAL = 'every_15_minutes';

	/**
	 * Add custom cron schedule (15 minutes).
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_custom_schedule( $schedules ) {
		$schedules[ self::SCHEDULE_INTERVAL ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'hypercart-server-monitor' ),
		);
		return $schedules;
	}

	/**
	 * Schedule the monitoring task.
	 */
	public function schedule() {
		// Check if already scheduled.
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			\Hypercart_Logger::debug(
				'hypercart-server-monitor',
				'Cron already scheduled',
				array( 'next_run' => wp_next_scheduled( self::CRON_HOOK ) )
			);
			return;
		}

		// Schedule event.
		$scheduled = wp_schedule_event(
			\Hypercart_Time::now(),
			self::SCHEDULE_INTERVAL,
			self::CRON_HOOK
		);

		if ( false === $scheduled ) {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Failed to schedule cron event',
				array()
			);
			return;
		}

		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'Cron event scheduled',
			array(
				'interval'  => self::SCHEDULE_INTERVAL,
				'next_run'  => wp_next_scheduled( self::CRON_HOOK ),
			)
		);
	}

	/**
	 * Unschedule the monitoring task.
	 */
	public function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );

			\Hypercart_Logger::info(
				'hypercart-server-monitor',
				'Cron event unscheduled',
				array()
			);
		}
	}

	/**
	 * Get next scheduled run time.
	 *
	 * @return int|false Next run timestamp or false if not scheduled.
	 */
	public function get_next_run() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Check if cron is scheduled.
	 *
	 * @return bool True if scheduled, false otherwise.
	 */
	public function is_scheduled() {
		return false !== wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Get cron status for debugging.
	 *
	 * @return array Status information.
	 */
	public function get_status() {
		$next_run = $this->get_next_run();

		return array(
			'is_scheduled' => $this->is_scheduled(),
			'next_run_utc' => $next_run ? $next_run : null,
			'next_run_local' => $next_run ? \Hypercart_Time::format( 'Y-m-d H:i:s', $next_run ) : null,
			'interval'     => self::SCHEDULE_INTERVAL,
			'hook_name'    => self::CRON_HOOK,
		);
	}
}

