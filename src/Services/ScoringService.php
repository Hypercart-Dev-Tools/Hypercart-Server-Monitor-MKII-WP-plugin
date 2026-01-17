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
	 * Baseline benchmark time in milliseconds for a "good" score.
	 */
	const BASELINE_MS = 100.0;

	/**
	 * Score for a benchmark at or better than the baseline.
	 */
	const MAX_SCORE = 100;

	/**
	 * Score at 2x the baseline time.
	 */
	const TIER_2_SCORE = 50;

	/**
	 * Score at 4x the baseline time.
	 */
	const TIER_3_SCORE = 20;

	/**
	 * A minimal score for very slow but completed benchmarks.
	 */
	const MINIMAL_SCORE = 10;

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
	 * Uses BASELINE_MS as reference point.
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

		if ( $time_ms <= self::BASELINE_MS ) {
			return self::MAX_SCORE;
		}

		$double_baseline = self::BASELINE_MS * 2;
		if ( $time_ms <= $double_baseline ) {
			// Linear from 100 to 50.
			$ratio = ( $time_ms - self::BASELINE_MS ) / self::BASELINE_MS;
			$score_decay = ( self::MAX_SCORE - self::TIER_2_SCORE );
			return (int) round( self::MAX_SCORE - ( $ratio * $score_decay ) );
		}

		$quad_baseline = self::BASELINE_MS * 4;
		if ( $time_ms <= $quad_baseline ) {
			// Linear from 50 to 20.
			$ratio = ( $time_ms - $double_baseline ) / ( $double_baseline );
			$score_decay = ( self::TIER_2_SCORE - self::TIER_3_SCORE );
			return (int) round( self::TIER_2_SCORE - ( $ratio * $score_decay ) );
		}

		// Very slow, but give some points for completing.
		if ( $time_ms <= self::BASELINE_MS * 10 ) {
			return self::MINIMAL_SCORE;
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

