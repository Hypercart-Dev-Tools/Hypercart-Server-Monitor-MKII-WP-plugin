<?php
/**
 * FSM State Store
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Domain;

/**
 * FSM-light state store using WordPress options.
 */
class FsmStateStore {
	/**
	 * Option name for state storage.
	 */
	const OPTION_NAME = 'hypercart_server_monitor_state';

	/**
	 * Valid states.
	 *
	 * @var array
	 */
	private $valid_states = array(
		'idle',
		'scheduled',
		'running',
		'writing',
		'emailing',
		'completed',
		'error',
		'tripped',
	);

	/**
	 * Initialize state store (on activation).
	 */
	public function initialize() {
		$initial_state = $this->get_default_state();

		update_option( self::OPTION_NAME, $initial_state, false );

		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'FSM state store initialized',
			array( 'initial_state' => 'idle' )
		);
	}

	/**
	 * Get current state data.
	 *
	 * @return array State data.
	 */
	public function get_state_data() {
		$state = get_option( self::OPTION_NAME, array() );

		// Ensure all keys exist.
		return wp_parse_args( $state, $this->get_default_state() );
	}

	/**
	 * Get the default state array.
	 *
	 * @return array
	 */
	private function get_default_state() {
		return array(
			'state'            => 'idle',
			'updated_utc'      => \Hypercart_Time::now(),
			'failure_count'    => 0,
			'last_error'       => null,
			'last_run_utc'     => null,
			'last_duration_ms' => null,
			'lock_status'      => 'unlocked',
			'lock_acquired_at' => null,
		);
	}

	/**
	 * Get current state name.
	 *
	 * @return string Current state.
	 */
	public function get_current_state() {
		$data = $this->get_state_data();
		return $data['state'];
	}

	/**
	 * Transition to a new state.
	 *
	 * @param string $new_state New state name.
	 * @param array  $metadata  Optional metadata to update.
	 * @return bool True on success, false on failure.
	 */
	public function transition_to( $new_state, $metadata = array() ) {
		// Validate state.
		if ( ! in_array( $new_state, $this->valid_states, true ) ) {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Invalid state transition attempted',
				array(
					'new_state' => $new_state,
					'valid_states' => $this->valid_states,
				)
			);
			return false;
		}

		$current_data = $this->get_state_data();
		$old_state    = $current_data['state'];

		// Update state.
		$new_data = array_merge(
			$current_data,
			$metadata,
			array(
				'state'       => $new_state,
				'updated_utc' => \Hypercart_Time::now(),
			)
		);

		update_option( self::OPTION_NAME, $new_data, false );

		\Hypercart_Logger::debug(
			'hypercart-server-monitor',
			'State transition',
			array(
				'from' => $old_state,
				'to'   => $new_state,
			)
		);

		return true;
	}

	/**
	 * Record an error.
	 *
	 * @param string $error_message Error message.
	 */
	public function record_error( $error_message ) {
		$data = $this->get_state_data();

		$data['failure_count']++;
		$data['last_error'] = array(
			'message'   => $error_message,
			'timestamp' => \Hypercart_Time::now(),
		);

		// Trip circuit breaker after 5 consecutive failures.
		if ( $data['failure_count'] >= 5 ) {
			$data['state'] = 'tripped';
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Circuit breaker tripped',
				array(
					'failure_count' => $data['failure_count'],
					'last_error'    => $error_message,
				)
			);
		}

		update_option( self::OPTION_NAME, $data, false );
	}

	/**
	 * Reset failure counter (on successful run).
	 */
	public function reset_failures() {
		$data                   = $this->get_state_data();
		$data['failure_count']  = 0;
		$data['last_error']     = null;

		update_option( self::OPTION_NAME, $data, false );
	}
}

