<?php
/**
 * Benchmark Collector
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Metrics;

/**
 * Runs synthetic PHP performance benchmark.
 *
 * Executes compute-intensive tasks (math, string ops, array ops) 3 times
 * and returns average execution time. Lower is better.
 */
class BenchmarkCollector {
	/**
	 * Max benchmark runtime (seconds).
	 */
	const MAX_RUNTIME_SECONDS = 10;

	/**
	 * Number of iterations to run.
	 *
	 * @var int
	 */
	private $iterations = 3;

	/**
	 * Collect benchmark metrics.
	 *
	 * @return array Benchmark data with execution times.
	 */
	public function collect() {
		\Hypercart_Logger::debug(
			'hypercart-server-monitor',
			'Starting benchmark collection',
			array( 'iterations' => $this->iterations )
		);

		$benchmark_start = microtime( true );
		$max_runtime = (int) apply_filters( 'hypercart_server_monitor_benchmark_timeout', self::MAX_RUNTIME_SECONDS );
		$max_runtime = max( 1, $max_runtime );

		$times = array();

		for ( $i = 0; $i < $this->iterations; $i++ ) {
			$iteration_start = microtime( true );

			// Run benchmark tasks.
			$this->run_math_operations();
			$this->run_string_operations();
			$this->run_array_operations();

			$end_time = microtime( true );
			$times[]  = ( $end_time - $iteration_start ) * 1000; // Convert to milliseconds.

			if ( ( microtime( true ) - $benchmark_start ) > $max_runtime ) {
				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'Benchmark timeout exceeded',
					array(
						'max_seconds' => $max_runtime,
						'elapsed_ms'  => round( ( microtime( true ) - $benchmark_start ) * 1000, 2 ),
					)
				);
				throw new \Exception( 'Benchmark timeout exceeded' );
			}
		}

		$avg_time = array_sum( $times ) / count( $times );
		$min_time = min( $times );
		$max_time = max( $times );

		\Hypercart_Logger::debug(
			'hypercart-server-monitor',
			'Benchmark completed',
			array(
				'avg_ms' => round( $avg_time, 2 ),
				'min_ms' => round( $min_time, 2 ),
				'max_ms' => round( $max_time, 2 ),
				'times'  => array_map( function( $t ) {
					return round( $t, 2 );
				}, $times ),
			)
		);

		return array(
			'supported'    => true,
			'avg_time_ms'  => $avg_time,
			'min_time_ms'  => $min_time,
			'max_time_ms'  => $max_time,
			'iterations'   => $this->iterations,
			'all_times_ms' => $times,
		);
	}

	/**
	 * Run mathematical operations.
	 */
	private function run_math_operations() {
		$result = 0;
		for ( $i = 0; $i < 50000; $i++ ) {
			$result += sqrt( $i ) * sin( $i ) * cos( $i );
		}
		return $result;
	}

	/**
	 * Run string operations.
	 */
	private function run_string_operations() {
		$str = '';
		for ( $i = 0; $i < 10000; $i++ ) {
			$str .= md5( (string) $i );
			$str  = substr( $str, 0, 1000 ); // Keep string from growing too large.
		}
		return strlen( $str );
	}

	/**
	 * Run array operations.
	 */
	private function run_array_operations() {
		$arr = array();
		for ( $i = 0; $i < 10000; $i++ ) {
			$arr[] = $i;
		}

		// Sort and filter.
		rsort( $arr );
		$filtered = array_filter( $arr, function( $n ) {
			return $n % 2 === 0;
		});

		// Map and reduce.
		$mapped = array_map( function( $n ) {
			return $n * 2;
		}, $filtered );

		return array_sum( $mapped );
	}
}
