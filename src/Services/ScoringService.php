<?php
/**
 * Scoring Service
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Services;

/**
 * Converts raw metrics to score (0-100).
 *
 * Now uses a single synthetic benchmark instead of multiple system metrics.
 */
class ScoringService {
	/**
	 * Baseline benchmark time in milliseconds.
	 *
	 * This represents "good" performance. Faster = higher score, slower = lower score.
	 *
	 * @var float
	 */
	private $baseline_ms = 100.0;

	/**
	 * Calculate score from benchmark metrics.
	 *
	 * @param array $raw_metrics Raw metric values from BenchmarkCollector.
	 * @param bool  $detailed    Whether to return detailed array or just integer.
	 * @return int|array Score (0-100) or detailed array.
	 */
	public function calculate_score( $raw_metrics, $detailed = false ) {
		// Extract benchmark time.
		$avg_time_ms = null;

		if ( isset( $raw_metrics['benchmark'] ) && is_array( $raw_metrics['benchmark'] ) ) {
			// New benchmark format.
			if ( $raw_metrics['benchmark']['supported'] ) {
				$avg_time_ms = $raw_metrics['benchmark']['avg_time_ms'] ?? null;
			}
		}

		// If no benchmark available, return 0.
		if ( null === $avg_time_ms ) {
			\Hypercart_Logger::warning(
				'hypercart-server-monitor',
				'No benchmark data available for scoring',
				array( 'raw_metrics' => $raw_metrics )
			);

			if ( $detailed ) {
				return array(
					'combined'     => 0,
					'benchmark_ms' => null,
					'label'        => 'Unknown',
				);
			}

			return 0;
		}

		// Calculate score based on benchmark time.
		$score = $this->score_benchmark( $avg_time_ms );

		\Hypercart_Logger::debug(
			'hypercart-server-monitor',
			'Score calculated from benchmark',
			array(
				'avg_time_ms' => $avg_time_ms,
				'score'       => $score,
			)
		);

		// Return detailed array if requested.
		if ( $detailed ) {
			return array(
				'combined'     => $score,
				'benchmark_ms' => $avg_time_ms,
				'label'        => $this->get_score_label( $score ),
			);
		}

		return $score;
	}

	/**
	 * Score benchmark execution time.
	 *
	 * Lower execution time = higher score.
	 * Uses baseline_ms as reference point.
	 *
	 * @param float $time_ms Average execution time in milliseconds.
	 * @return int Score (0-100).
	 */
	private function score_benchmark( $time_ms ) {
		// Scoring logic:
		// <= baseline → 100
		// baseline to 2x baseline → linear down to 50
		// 2x to 4x baseline → linear down to 20
		// > 4x baseline → 0

		if ( $time_ms <= $this->baseline_ms ) {
			return 100;
		}

		$double_baseline = $this->baseline_ms * 2;
		if ( $time_ms <= $double_baseline ) {
			// Linear from 100 to 50.
			$ratio = ( $time_ms - $this->baseline_ms ) / $this->baseline_ms;
			return (int) round( 100 - ( $ratio * 50 ) );
		}

		$quad_baseline = $this->baseline_ms * 4;
		if ( $time_ms <= $quad_baseline ) {
			// Linear from 50 to 20.
			$ratio = ( $time_ms - $double_baseline ) / ( $double_baseline );
			return (int) round( 50 - ( $ratio * 30 ) );
		}

		// Very slow, but give some points for completing.
		if ( $time_ms <= $this->baseline_ms * 10 ) {
			return 10;
		}

		return 0;
	}

	/**
	 * Get score label.
	 *
	 * @param int $score Combined score.
	 * @return string Label (Excellent/Good/Warning/Critical).
	 */
	public function get_score_label( $score ) {
		if ( $score >= 90 ) {
			return 'Excellent';
		}
		if ( $score >= 75 ) {
			return 'Good';
		}
		if ( $score >= 60 ) {
			return 'Warning';
		}
		return 'Critical';
	}
}

