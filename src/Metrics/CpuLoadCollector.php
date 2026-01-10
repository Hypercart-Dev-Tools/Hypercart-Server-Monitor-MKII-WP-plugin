<?php
/**
 * CPU Load Collector
 *
 * @package WP_Server_Monitor
 */

namespace WP_Server_Monitor\Metrics;

/**
 * Collects CPU load average (1-minute, normalized by cores).
 */
class CpuLoadCollector implements MetricsCollectorInterface {
	/**
	 * Collect CPU load metric.
	 *
	 * @return float|string Normalized load average or 'unknown'.
	 */
	public function collect() {
		if ( ! $this->is_supported() ) {
			\Hypercart_Logger::warning(
				'wp-server-monitor',
				'CPU load collection not supported',
				array()
			);
			return 'unknown';
		}

		$load = sys_getloadavg();
		if ( false === $load || ! isset( $load[0] ) ) {
			\Hypercart_Logger::warning(
				'wp-server-monitor',
				'Failed to get load average',
				array()
			);
			return 'unknown';
		}

		$load_1min = $load[0];
		$cores     = $this->get_cpu_cores();

		// Normalize by cores.
		$normalized = $cores > 0 ? $load_1min / $cores : $load_1min;

		\Hypercart_Logger::debug(
			'wp-server-monitor',
			'CPU load collected',
			array(
				'load_1min'  => $load_1min,
				'cores'      => $cores,
				'normalized' => $normalized,
			)
		);

		return round( $normalized, 2 );
	}

	/**
	 * Get metric name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cpu_load_1m_norm';
	}

	/**
	 * Check if supported.
	 *
	 * @return bool
	 */
	public function is_supported() {
		return function_exists( 'sys_getloadavg' );
	}

	/**
	 * Get number of CPU cores.
	 *
	 * @return int Number of cores (fallback to 1).
	 */
	private function get_cpu_cores() {
		// Try WordPress function if available (WP 5.6+).
		if ( function_exists( 'wp_get_cpu_count' ) ) {
			return wp_get_cpu_count();
		}

		// Fallback: try to detect from /proc/cpuinfo (Linux).
		if ( is_readable( '/proc/cpuinfo' ) ) {
			$cpuinfo = file_get_contents( '/proc/cpuinfo' );
			preg_match_all( '/^processor/m', $cpuinfo, $matches );
			$cores = count( $matches[0] );
			if ( $cores > 0 ) {
				return $cores;
			}
		}

		// Default fallback.
		return 1;
	}
}

