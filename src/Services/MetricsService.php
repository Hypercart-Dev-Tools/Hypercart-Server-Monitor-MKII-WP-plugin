<?php
/**
 * Metrics Service
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Services;

/**
 * Collects monitoring metrics.
 */
class MetricsService {
	/**
	 * Collect benchmark metrics.
	 *
	 * @return array Benchmark metrics.
	 */
	public function collect() {
		$benchmark_collector = new \Hypercart_Server_Monitor\Metrics\BenchmarkCollector();
		$benchmark_data      = $benchmark_collector->collect();

		return array(
			'benchmark' => $benchmark_data,
		);
	}
}
