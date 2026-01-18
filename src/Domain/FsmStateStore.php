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
	 * Failure threshold before tripping the breaker.
	 */
	const FAILURE_THRESHOLD = 5;

	/**
	 * Cooldown window after a trip (seconds).
	 */
	const COOLDOWN_SECONDS = 900;

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
		'half_open',
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
			'cooldown_until'   => null,
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
				$current_data = $this->get_state_data();
				return $this->update_state_locked( $current_data, $new_state, $metadata );
			}
		);
	}

	/**
	 * Determine whether a run is allowed based on breaker state.
	 *
	 * Note: The circuit breaker flow is centralized here; avoid refactoring
	 * without updating the monitoring run sequence and tests.
	 *
	 * @param bool $allow_probe Whether to allow a probe run after cooldown.
	 * @return array Result with allowed/probe/message keys.
	 */
	public function begin_run( $allow_probe = true ) {
		return $this->with_state_lock(
			function () use ( $allow_probe ) {
				$data  = $this->get_state_data();
				$state = $data['state'] ?? 'idle';
				$now   = \Hypercart_Time::now();

				if ( 'tripped' === $state ) {
					$cooldown_until = $data['cooldown_until'] ?? null;
					if ( $cooldown_until && $now < (int) $cooldown_until ) {
						\Hypercart_Logger::warning(
							'hypercart-server-monitor',
							'Circuit breaker cooldown active, run blocked',
							array(
								'cooldown_until' => $cooldown_until,
								'remaining_sec'  => (int) $cooldown_until - $now,
							)
						);
						return array(
							'allowed' => false,
							'probe'   => false,
							'message' => __( 'Circuit breaker cooldown active. Run skipped.', 'hypercart-server-monitor' ),
						);
					}

					if ( ! $allow_probe ) {
						\Hypercart_Logger::warning(
							'hypercart-server-monitor',
							'Circuit breaker requires probe run, blocking normal run',
							array()
						);
						return array(
							'allowed' => false,
							'probe'   => false,
							'message' => __( 'Circuit breaker requires a probe run. Run skipped.', 'hypercart-server-monitor' ),
						);
					}

					$this->update_state_locked(
						$data,
						'half_open',
						array( 'cooldown_until' => null )
					);

					return array(
						'allowed' => true,
						'probe'   => true,
						'message' => null,
					);
				}

				if ( 'half_open' === $state ) {
					if ( ! $allow_probe ) {
						\Hypercart_Logger::warning(
							'hypercart-server-monitor',
							'Probe-only state active, blocking normal run',
							array()
						);
						return array(
							'allowed' => false,
							'probe'   => false,
							'message' => __( 'Circuit breaker is in probe mode. Run skipped.', 'hypercart-server-monitor' ),
						);
					}

					return array(
						'allowed' => true,
						'probe'   => true,
						'message' => null,
					);
				}

				return array(
					'allowed' => true,
					'probe'   => false,
					'message' => null,
				);
			}
		);
	}

	/**
	 * Record an error.
	 *
	 * @param string $error_message Error message.
	 * @return array Result with tripped flag.
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

				if ( $data['failure_count'] >= self::FAILURE_THRESHOLD ) {
					$this->trip_locked( $data, $error_message, 'failure_threshold' );
					return array( 'tripped' => true );
				}

				$this->update_state_data_locked( $data );

				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'Run failure recorded',
					array(
						'failure_count' => $data['failure_count'],
						'error'         => $error_message,
					)
				);

				return array( 'tripped' => false );
			}
		);
	}

	/**
	 * Record a probe failure and trip immediately.
	 *
	 * @param string $error_message Error message.
	 * @return array Result with tripped flag.
	 */
	public function record_probe_failure( $error_message ) {
		return $this->with_state_lock(
			function () use ( $error_message ) {
				$data = $this->get_state_data();

				$data['failure_count']++;
				$this->trip_locked( $data, $error_message, 'probe_failure' );

				return array( 'tripped' => true );
			}
		);
	}

	/**
	 * Record a probe success and close the breaker.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function record_probe_success() {
		return $this->with_state_lock(
			function () {
				$data                  = $this->get_state_data();
				$data['failure_count'] = 0;
				$data['last_error']    = null;
				$data['cooldown_until']= null;

				$this->update_state_data_locked( $data );

				\Hypercart_Logger::info(
					'hypercart-server-monitor',
					'Circuit breaker closed after probe success',
					array()
				);

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
				$data['cooldown_until'] = null;

				$this->update_state_data_locked( $data );

				return true;
			}
		);
	}

	/**
	 * Run a breaker self test and restore the original state.
	 *
	 * @return array Test result payload.
	 */
	public function run_breaker_self_test() {
		return $this->with_state_lock(
			function () {
				$data          = $this->get_state_data();
				$current_state = $data['state'] ?? 'idle';

				if ( in_array( $current_state, array( 'running', 'writing', 'emailing' ), true ) ) {
					\Hypercart_Logger::warning(
						'hypercart-server-monitor',
						'Breaker self test blocked during active run',
						array( 'state' => $current_state )
					);
					return array(
						'success' => false,
						'message' => __( 'Breaker self test blocked during an active run.', 'hypercart-server-monitor' ),
					);
				}

				$original = $data;
				$steps    = array();
				$now      = \Hypercart_Time::now();

				$data['failure_count'] = max( $data['failure_count'], self::FAILURE_THRESHOLD );
				$this->trip_locked( $data, 'Self test trip', 'self_test' );
				$steps[] = array(
					'step'   => __( 'Trip breaker', 'hypercart-server-monitor' ),
					'status' => 'ok',
				);

				$data = $this->get_state_data();
				$data['cooldown_until'] = $now - 1;
				$this->update_state_data_locked( $data );
				$data = $this->get_state_data();
				$this->update_state_locked( $data, 'half_open', array( 'cooldown_until' => null ) );
				$steps[] = array(
					'step'   => __( 'Enter half-open (probe allowed)', 'hypercart-server-monitor' ),
					'status' => 'ok',
				);

				$data = $this->get_state_data();
				$data['failure_count'] = max( $data['failure_count'] + 1, self::FAILURE_THRESHOLD );
				$this->trip_locked( $data, 'Self test probe failure', 'probe_failure' );
				$steps[] = array(
					'step'   => __( 'Probe failure re-trips', 'hypercart-server-monitor' ),
					'status' => 'ok',
				);

				$data = $this->get_state_data();
				$data['cooldown_until'] = $now - 1;
				$this->update_state_data_locked( $data );
				$data = $this->get_state_data();
				$this->update_state_locked( $data, 'half_open', array( 'cooldown_until' => null ) );
				$steps[] = array(
					'step'   => __( 'Cooldown expiry allows probe', 'hypercart-server-monitor' ),
					'status' => 'ok',
				);

				$data = $this->get_state_data();
				$this->update_state_locked(
					$data,
					'idle',
					array(
						'failure_count'  => 0,
						'last_error'     => null,
						'cooldown_until' => null,
					)
				);
				$steps[] = array(
					'step'   => __( 'Probe success closes breaker', 'hypercart-server-monitor' ),
					'status' => 'ok',
				);

				update_option( self::OPTION_NAME, $original, false );

				\Hypercart_Logger::info(
					'hypercart-server-monitor',
					'Breaker self test completed; state restored',
					array(
						'state' => $original['state'] ?? 'unknown',
					)
				);

				return array(
					'success' => true,
					'steps'   => $steps,
					'message' => __( 'Breaker self test completed and state restored.', 'hypercart-server-monitor' ),
				);
			}
		);
	}

	/**
	 * Update state while holding the state lock.
	 *
	 * @param array  $current_data Current state data.
	 * @param string $new_state    New state.
	 * @param array  $metadata     Metadata to merge.
	 * @return bool True on success, false on failure.
	 */
	private function update_state_locked( $current_data, $new_state, $metadata = array() ) {
		if ( ! in_array( $new_state, $this->valid_states, true ) ) {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Invalid state transition attempted',
				array(
					'new_state'    => $new_state,
					'valid_states' => $this->valid_states,
				)
			);
			return false;
		}

		$old_state = $current_data['state'] ?? 'unknown';
		$new_data  = array_merge(
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
	 * Update state data without changing the state.
	 *
	 * @param array $data Current state data.
	 * @return bool True on success, false on failure.
	 */
	private function update_state_data_locked( $data ) {
		$data['updated_utc'] = \Hypercart_Time::now();
		update_option( self::OPTION_NAME, $data, false );
		return true;
	}

	/**
	 * Trip the breaker and set cooldown.
	 *
	 * @param array  $data          Current state data.
	 * @param string $error_message Error message.
	 * @param string $reason        Trip reason.
	 * @return bool True on success, false on failure.
	 */
	private function trip_locked( $data, $error_message, $reason ) {
		$cooldown_until = \Hypercart_Time::now() + self::COOLDOWN_SECONDS;

		$metadata = array(
			'failure_count' => $data['failure_count'],
			'last_error'    => array(
				'message'   => $error_message,
				'timestamp' => \Hypercart_Time::now(),
			),
			'cooldown_until' => $cooldown_until,
		);

		\Hypercart_Logger::warning(
			'hypercart-server-monitor',
			'Circuit breaker tripped',
			array(
				'failure_count'  => $data['failure_count'],
				'reason'         => $reason,
				'cooldown_until' => $cooldown_until,
			)
		);

		return $this->update_state_locked( $data, 'tripped', $metadata );
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
