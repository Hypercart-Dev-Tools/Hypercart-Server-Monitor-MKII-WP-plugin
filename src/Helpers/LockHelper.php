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
	 * Lock token for this process.
	 *
	 * @var string|null
	 */
	private static $token = null;

	/**
	 * Acquire lock.
	 *
	 * @return bool True if lock acquired, false if already locked.
	 */
	public static function acquire() {
		if ( null !== self::$token ) {
			return true;
		}

		$now        = \Hypercart_Time::now();
		$token      = wp_generate_uuid4();
		$lock_data  = array(
			'token'      => $token,
			'acquired_at'=> $now,
			'expires_at' => $now + self::LOCK_TTL,
			'pid'        => getmypid(),
		);
		$acquired   = add_option( self::LOCK_NAME, $lock_data, '', 'no' );

		if ( ! $acquired ) {
			$existing = get_option( self::LOCK_NAME );
			$expired  = is_array( $existing ) && ! empty( $existing['expires_at'] )
				? ( (int) $existing['expires_at'] <= $now )
				: false;

			if ( $expired && delete_option( self::LOCK_NAME ) ) {
				$acquired = add_option( self::LOCK_NAME, $lock_data, '', 'no' );
			}
		}

		if ( $acquired ) {
			self::$token = $token;
			\Hypercart_Logger::debug(
				'hypercart-server-monitor',
				'Lock acquired',
				array( 'ttl' => self::LOCK_TTL )
			);
		} else {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Lock already held',
				array( 'lock' => self::LOCK_NAME )
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
		if ( null === self::$token ) {
			return false;
		}

		$lock_data = get_option( self::LOCK_NAME );
		if ( ! is_array( $lock_data ) || ( $lock_data['token'] ?? null ) !== self::$token ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Lock token mismatch on release',
				array( 'lock' => self::LOCK_NAME )
			);
			return false;
		}

		$released    = delete_option( self::LOCK_NAME );
		self::$token = null;

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
		$lock_data = get_option( self::LOCK_NAME );
		if ( ! is_array( $lock_data ) ) {
			return false;
		}

		$expires_at = $lock_data['expires_at'] ?? null;
		if ( $expires_at && (int) $expires_at <= \Hypercart_Time::now() ) {
			delete_option( self::LOCK_NAME );
			return false;
		}

		return true;
	}

	/**
	 * Get lock status for debugging.
	 *
	 * @return array Lock status.
	 */
	public static function get_status() {
		$lock_data = get_option( self::LOCK_NAME );

		if ( ! is_array( $lock_data ) ) {
			return array(
				'locked'       => false,
				'acquired_at'  => null,
				'age_seconds'  => null,
			);
		}

		$acquired_at = $lock_data['acquired_at'] ?? null;
		$expires_at  = $lock_data['expires_at'] ?? null;
		$age         = $acquired_at ? ( \Hypercart_Time::now() - $acquired_at ) : null;
		$expired     = $expires_at ? ( (int) $expires_at <= \Hypercart_Time::now() ) : false;

		return array(
			'locked'       => ! $expired,
			'acquired_at'  => $acquired_at,
			'age_seconds'  => $age,
			'ttl_seconds'  => self::LOCK_TTL,
		);
	}
}
