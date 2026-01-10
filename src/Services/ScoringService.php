<?php
/**
 * Scoring Service
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Services;

/**
 * Converts raw metrics to subscores and combined score (0-100).
 */
class ScoringService {
	/**
	 * Metric weights (must sum to 100).
	 *
	 * @var array
	 */
	private $weights = array(
		'cpu_load_1m_norm' => 40,
		'mem_used_pct'     => 35,
		'disk_free_pct'    => 25,
	);

	/**
	 * Calculate combined score from raw metrics.
	 *
	 * @param array $raw_metrics Raw metric values.
	 * @return int Combined score (0-100).
	 */
	public function calculate_score( $raw_metrics ) {
		$subscores = array(
			'cpu'  => $this->score_cpu_load( $raw_metrics['cpu_load_1m_norm'] ?? 'unknown' ),
			'mem'  => $this->score_memory( $raw_metrics['mem_used_pct'] ?? 'unknown' ),
			'disk' => $this->score_disk( $raw_metrics['disk_free_pct'] ?? 'unknown' ),
		);

		// Calculate weighted average.
		$total_weight = 0;
		$weighted_sum = 0;

		if ( 'unknown' !== $subscores['cpu'] ) {
			$weighted_sum += $subscores['cpu'] * $this->weights['cpu_load_1m_norm'];
			$total_weight += $this->weights['cpu_load_1m_norm'];
		}

		if ( 'unknown' !== $subscores['mem'] ) {
			$weighted_sum += $subscores['mem'] * $this->weights['mem_used_pct'];
			$total_weight += $this->weights['mem_used_pct'];
		}

		if ( 'unknown' !== $subscores['disk'] ) {
			$weighted_sum += $subscores['disk'] * $this->weights['disk_free_pct'];
			$total_weight += $this->weights['disk_free_pct'];
		}

		// If no metrics available, return 0.
		if ( $total_weight === 0 ) {
			return 0;
		}

		$combined = $weighted_sum / $total_weight;

		\Hypercart_Logger::debug(
			'hypercart-server-monitor',
			'Score calculated',
			array(
				'subscores' => $subscores,
				'combined'  => $combined,
			)
		);

		return (int) round( $combined );
	}

	/**
	 * Score CPU load (normalized).
	 *
	 * @param float|string $load Normalized load average.
	 * @return int|string Score (0-100) or 'unknown'.
	 */
	private function score_cpu_load( $load ) {
		if ( 'unknown' === $load ) {
			return 'unknown';
		}

		// <= 0.7 → 100
		if ( $load <= 0.7 ) {
			return 100;
		}

		// 0.7-1.0 → linear down to 70
		if ( $load <= 1.0 ) {
			return (int) round( 100 - ( ( $load - 0.7 ) / 0.3 ) * 30 );
		}

		// 1.0-2.0 → linear down to 20
		if ( $load <= 2.0 ) {
			return (int) round( 70 - ( ( $load - 1.0 ) / 1.0 ) * 50 );
		}

		// > 2.0 → 0
		return 0;
	}

	/**
	 * Score memory usage percentage.
	 *
	 * @param float|string $used_pct Memory used percentage.
	 * @return int|string Score (0-100) or 'unknown'.
	 */
	private function score_memory( $used_pct ) {
		if ( 'unknown' === $used_pct ) {
			return 'unknown';
		}

		// <= 70% → 100
		if ( $used_pct <= 70 ) {
			return 100;
		}

		// 70-85% → linear down to 60
		if ( $used_pct <= 85 ) {
			return (int) round( 100 - ( ( $used_pct - 70 ) / 15 ) * 40 );
		}

		// 85-95% → linear down to 20
		if ( $used_pct <= 95 ) {
			return (int) round( 60 - ( ( $used_pct - 85 ) / 10 ) * 40 );
		}

		// > 95% → 0
		return 0;
	}

	/**
	 * Score disk free space percentage.
	 *
	 * @param float|string $free_pct Disk free percentage.
	 * @return int|string Score (0-100) or 'unknown'.
	 */
	private function score_disk( $free_pct ) {
		if ( 'unknown' === $free_pct ) {
			return 'unknown';
		}

		// >= 20% → 100
		if ( $free_pct >= 20 ) {
			return 100;
		}

		// 10-20% → linear down to 50
		if ( $free_pct >= 10 ) {
			return (int) round( 50 + ( ( $free_pct - 10 ) / 10 ) * 50 );
		}

		// 5-10% → linear down to 20
		if ( $free_pct >= 5 ) {
			return (int) round( 20 + ( ( $free_pct - 5 ) / 5 ) * 30 );
		}

		// < 5% → 0
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

