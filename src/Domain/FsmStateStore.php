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
	 * Option name for state lock.
	 */
	const LOCK_NAME = 'hypercart_server_monitor_state_lock';

	/**
	 * Lock TTL in seconds.
	 */
	const LOCK_TTL = 30;

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
		return $this->with_state_lock(
			function () use ( $new_state, $metadata ) {
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
		);
	}

	/**
	 * Record an error.
	 *
	 * @param string $error_message Error message.
	 */
	public function record_error( $error_message ) {
		return $this->with_state_lock(
			function () use ( $error_message ) {
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

				return true;
			}
		);
	}

	/**
	 * Reset failure counter (on successful run).
	 */
	public function reset_failures() {
		return $this->with_state_lock(
			function () {
				$data                   = $this->get_state_data();
				$data['failure_count']  = 0;
				$data['last_error']     = null;

				update_option( self::OPTION_NAME, $data, false );

				return true;
			}
		);
	}

	/**
	 * Execute a state update within a lock.
	 *
	 * @param callable $callback Callback to execute while locked.
	 * @return mixed Callback result or false if lock not acquired.
	 */
	private function with_state_lock( $callback ) {
		$token = $this->acquire_state_lock();
		if ( ! $token ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Failed to acquire state lock',
				array( 'lock' => self::LOCK_NAME )
			);
			return false;
		}

		try {
			return call_user_func( $callback );
		} finally {
			$this->release_state_lock( $token );
		}
	}

	/**
	 * Acquire the state lock.
	 *
	 * @return string|false Token on success, false on failure.
	 */
	private function acquire_state_lock() {
		$now       = \Hypercart_Time::now();
		$token     = wp_generate_uuid4();
		$lock_data = array(
			'token'      => $token,
			'expires_at' => $now + self::LOCK_TTL,
		);

		$acquired = add_option( self::LOCK_NAME, $lock_data, '', 'no' );
		if ( ! $acquired ) {
			$existing = get_option( self::LOCK_NAME );
			$expired  = is_array( $existing ) && ! empty( $existing['expires_at'] )
				? ( (int) $existing['expires_at'] <= $now )
				: false;

			if ( $expired && delete_option( self::LOCK_NAME ) ) {
				$acquired = add_option( self::LOCK_NAME, $lock_data, '', 'no' );
			}
		}

		return $acquired ? $token : false;
	}

	/**
	 * Release the state lock.
	 *
	 * @param string $token Lock token.
	 * @return bool True on success, false otherwise.
	 */
	private function release_state_lock( $token ) {
		$lock_data = get_option( self::LOCK_NAME );
		if ( ! is_array( $lock_data ) || ( $lock_data['token'] ?? null ) !== $token ) {
			return false;
		}

		return delete_option( self::LOCK_NAME );
	}
}
