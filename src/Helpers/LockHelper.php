<?php
/**
 * Lock Helper
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Helpers;

/**
 * Manages mutex locking using WordPress transients.
 */
class LockHelper {
	/**
	 * Lock transient name.
	 */
	const LOCK_NAME = 'hypercart_server_monitor_lock';

	/**
	 * Lock TTL (10 minutes).
	 */
	const LOCK_TTL = 600;

	/**
	 * Acquire lock.
	 *
	 * @return bool True if lock acquired, false if already locked.
	 */
	public static function acquire() {
		// Check if already locked.
		if ( get_transient( self::LOCK_NAME ) ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Lock already held',
				array( 'lock' => self::LOCK_NAME )
			);
			return false;
		}

		// Acquire lock.
		$acquired = set_transient(
			self::LOCK_NAME,
			array(
				'acquired_at' => \Hypercart_Time::now(),
				'pid'         => getmypid(),
			),
			self::LOCK_TTL
		);

		if ( $acquired ) {
			\Hypercart_Logger::debug(
				'hypercart-server-monitor',
				'Lock acquired',
				array( 'ttl' => self::LOCK_TTL )
			);
		}

		return $acquired;
	}

	/**
	 * Release lock.
	 *
	 * @return bool True if released, false otherwise.
	 */
	public static function release() {
		$released = delete_transient( self::LOCK_NAME );

		if ( $released ) {
			\Hypercart_Logger::debug(
				'hypercart-server-monitor',
				'Lock released',
				array()
			);
		}

		return $released;
	}

	/**
	 * Check if locked.
	 *
	 * @return bool True if locked, false otherwise.
	 */
	public static function is_locked() {
		return false !== get_transient( self::LOCK_NAME );
	}

	/**
	 * Get lock status for debugging.
	 *
	 * @return array Lock status.
	 */
	public static function get_status() {
		$lock_data = get_transient( self::LOCK_NAME );

		if ( ! $lock_data ) {
			return array(
				'locked'       => false,
				'acquired_at'  => null,
				'age_seconds'  => null,
			);
		}

		$acquired_at = $lock_data['acquired_at'] ?? null;
		$age         = $acquired_at ? ( \Hypercart_Time::now() - $acquired_at ) : null;

		return array(
			'locked'       => true,
			'acquired_at'  => $acquired_at,
			'age_seconds'  => $age,
			'ttl_seconds'  => self::LOCK_TTL,
		);
	}
}

