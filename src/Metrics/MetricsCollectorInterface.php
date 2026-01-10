<?php
/**
 * Metrics Collector Interface
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Metrics;

/**
 * Interface for metric collectors.
 */
interface MetricsCollectorInterface {
	/**
	 * Collect metric value.
	 *
	 * @return float|string Metric value or 'unknown' if unavailable.
	 */
	public function collect();

	/**
	 * Get metric name.
	 *
	 * @return string Metric name.
	 */
	public function get_name();

	/**
	 * Check if metric is supported on this system.
	 *
	 * @return bool True if supported, false otherwise.
	 */
	public function is_supported();
}

