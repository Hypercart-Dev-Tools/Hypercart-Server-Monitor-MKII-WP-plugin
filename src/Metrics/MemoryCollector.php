<?php
/**
 * Memory Collector
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Metrics;

/**
 * Collects memory usage percentage.
 */
class MemoryCollector implements MetricsCollectorInterface {
	/**
	 * Collect memory usage metric.
	 *
	 * @return float|string Memory used percentage or 'unknown'.
	 */
	public function collect() {
		if ( ! $this->is_supported() ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Memory collection not supported',
				array()
			);
			return 'unknown';
		}

		$meminfo = $this->parse_meminfo();
		if ( empty( $meminfo ) ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'Failed to parse /proc/meminfo',
				array()
			);
			return 'unknown';
		}

		$total     = isset( $meminfo['MemTotal'] ) ? $meminfo['MemTotal'] : 0;
		$available = isset( $meminfo['MemAvailable'] ) ? $meminfo['MemAvailable'] : 0;

		if ( $total <= 0 ) {
			return 'unknown';
		}

		$used_pct = ( ( $total - $available ) / $total ) * 100;

		\Hypercart_Logger::debug(
			'hypercart-server-monitor',
			'Memory usage collected',
			array(
				'total_kb'     => $total,
				'available_kb' => $available,
				'used_pct'     => $used_pct,
			)
		);

		return round( $used_pct, 1 );
	}

	/**
	 * Get metric name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'mem_used_pct';
	}

	/**
	 * Check if supported.
	 *
	 * @return bool
	 */
	public function is_supported() {
		return is_readable( '/proc/meminfo' );
	}

	/**
	 * Parse /proc/meminfo file.
	 *
	 * @return array Parsed memory info (key => value in KB).
	 */
	private function parse_meminfo() {
		if ( ! is_readable( '/proc/meminfo' ) ) {
			return array();
		}

		$content = file_get_contents( '/proc/meminfo' );
		if ( false === $content ) {
			return array();
		}

		$lines   = explode( "\n", $content );
		$meminfo = array();

		foreach ( $lines as $line ) {
			if ( preg_match( '/^(\w+):\s+(\d+)/', $line, $matches ) ) {
				$meminfo[ $matches[1] ] = (int) $matches[2];
			}
		}

		return $meminfo;
	}
}

