<?php
/**
 * Disk Collector
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Metrics;

/**
 * Collects disk free space percentage.
 */
class DiskCollector implements MetricsCollectorInterface {
	/**
	 * Collect disk free space metric.
	 *
	 * @return float|string Disk free percentage or 'unknown'.
	 */
	public function collect() {
		if ( ! $this->is_supported() ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Disk collection not supported',
				array()
			);
			return 'unknown';
		}

		$path = $this->get_check_path();

		$free  = @disk_free_space( $path );
		$total = @disk_total_space( $path );

		if ( false === $free || false === $total || $total <= 0 ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Failed to get disk space',
				array( 'path' => $path )
			);
			return 'unknown';
		}

		$free_pct = ( $free / $total ) * 100;

		\Hypercart_Logger::debug(
			'hypercart-server-monitor',
			'Disk space collected',
			array(
				'path'      => $path,
				'free_gb'   => round( $free / 1024 / 1024 / 1024, 2 ),
				'total_gb'  => round( $total / 1024 / 1024 / 1024, 2 ),
				'free_pct'  => $free_pct,
			)
		);

		return round( $free_pct, 1 );
	}

	/**
	 * Get metric name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'disk_free_pct';
	}

	/**
	 * Check if supported.
	 *
	 * @return bool
	 */
	public function is_supported() {
		return function_exists( 'disk_free_space' ) && function_exists( 'disk_total_space' );
	}

	/**
	 * Get path to check disk space.
	 *
	 * @return string Path to check.
	 */
	private function get_check_path() {
		// Prefer WP_CONTENT_DIR (where uploads live).
		if ( defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR ) ) {
			return WP_CONTENT_DIR;
		}

		// Fallback to ABSPATH.
		if ( defined( 'ABSPATH' ) && is_dir( ABSPATH ) ) {
			return ABSPATH;
		}

		// Last resort.
		return '/';
	}
}

