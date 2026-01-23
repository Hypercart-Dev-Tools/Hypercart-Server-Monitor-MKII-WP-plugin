<?php
/**
 * Frontend Dashboard Shortcode (Read-only)
 *
 * @package Hypercart_Server_Monitor
 * @var array|null $latest_sample Latest health sample.
 * @var array      $samples       All samples (24h).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php
// Safeguard: this frontend view must remain read-only (no writes or actions).
?>

<div class="hsm-dashboard hsm-dashboard-readonly">
	<?php if ( isset( $is_noindex_enabled ) && $is_noindex_enabled && current_user_can( 'manage_options' ) ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'Search Engine Indexing Disabled', 'hypercart-server-monitor' ); ?></strong><br />
				<?php esc_html_e( 'This page is not indexed by search engines. This setting can be changed by adding noindex="false" to the shortcode.', 'hypercart-server-monitor' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="notice notice-info inline">
		<p><?php esc_html_e( 'Read-only dashboard view', 'hypercart-server-monitor' ); ?></p>
	</div>
	<div class="notice notice-info inline">
		<p>
			<?php
			$timezone_string = wp_timezone_string();
			printf(
				/* translators: %s: timezone name */
				esc_html__( 'Note: Showing Local Time (%s). You may need to convert to UTC elsewhere.', 'hypercart-server-monitor' ),
				esc_html( $timezone_string )
			);
			?>
		</p>
	</div>
	<!-- Current Health Score -->
	<div class="hsm-card hsm-health-score">
		<h2><?php esc_html_e( 'Current Performance Score', 'hypercart-server-monitor' ); ?></h2>
		<p class="hsm-score-hint"><?php esc_html_e( 'Based on PHP benchmark execution time (lower time = higher score)', 'hypercart-server-monitor' ); ?></p>
		<?php if ( $latest_sample ) : ?>
			<?php
			$score       = $latest_sample['score'] ?? array();
			$combined    = $score['combined'] ?? 0;
			$label       = $score['label'] ?? 'Unknown';
			$timestamp   = $latest_sample['ts_utc'] ?? '';
			$raw_metrics = $latest_sample['raw'] ?? array();

			// Determine score color.
			$score_class = 'hsm-score-unknown';
			if ( $combined >= 90 ) {
				$score_class = 'hsm-score-excellent';
			} elseif ( $combined >= 75 ) {
				$score_class = 'hsm-score-good';
			} elseif ( $combined >= 60 ) {
				$score_class = 'hsm-score-warning';
			} else {
				$score_class = 'hsm-score-critical';
			}
			?>
			<div class="hsm-score-display <?php echo esc_attr( $score_class ); ?>">
				<div class="hsm-score-number"><?php echo esc_html( number_format( $combined, 1 ) ); ?></div>
				<div class="hsm-score-label"><?php echo esc_html( $label ); ?></div>
			</div>
			<p class="hsm-timestamp">
				<?php
				printf(
					/* translators: %s: timestamp */
					esc_html__( 'Last updated: %s', 'hypercart-server-monitor' ),
					esc_html( \Hypercart_Time::format( 'Y-m-d H:i:s', strtotime( $timestamp ) ) )
				);
				?>
			</p>

			<!-- Benchmark Details -->
			<?php
			$benchmark_avg        = (float) ( $raw_metrics['benchmark']['avg_time_ms'] ?? 0 );
			$benchmark_min        = (float) ( $raw_metrics['benchmark']['min_time_ms'] ?? 0 );
			$benchmark_max        = (float) ( $raw_metrics['benchmark']['max_time_ms'] ?? 0 );
			$benchmark_iterations = (int) ( $raw_metrics['benchmark']['iterations'] ?? 0 );
			?>
			<table class="widefat hsm-metrics-table" data-hsm-table="benchmark" data-hsm-sample-ts-utc="<?php echo esc_attr( $timestamp ); ?>">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Metric', 'hypercart-server-monitor' ); ?></th>
						<th><?php esc_html_e( 'Value', 'hypercart-server-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr data-hsm-metric="benchmark_avg_ms">
						<td data-hsm-label="benchmark_avg_ms"><?php esc_html_e( 'Benchmark Time (avg)', 'hypercart-server-monitor' ); ?> <span style="color: #666; font-size: 0.9em;">↓ <?php esc_html_e( 'lower is better', 'hypercart-server-monitor' ); ?></span></td>
						<td data-hsm-value="<?php echo esc_attr( $benchmark_avg ); ?>" data-hsm-unit="ms">
							<span class="hsm-metric-value" data-hsm-value><?php echo esc_html( number_format( $benchmark_avg, 2 ) ); ?></span>
							<span class="hsm-metric-unit" data-hsm-unit>ms</span>
						</td>
					</tr>
					<tr data-hsm-metric="benchmark_min_ms">
						<td data-hsm-label="benchmark_min_ms"><?php esc_html_e( 'Fastest Run', 'hypercart-server-monitor' ); ?></td>
						<td data-hsm-value="<?php echo esc_attr( $benchmark_min ); ?>" data-hsm-unit="ms">
							<span class="hsm-metric-value" data-hsm-value><?php echo esc_html( number_format( $benchmark_min, 2 ) ); ?></span>
							<span class="hsm-metric-unit" data-hsm-unit>ms</span>
						</td>
					</tr>
					<tr data-hsm-metric="benchmark_max_ms">
						<td data-hsm-label="benchmark_max_ms"><?php esc_html_e( 'Slowest Run', 'hypercart-server-monitor' ); ?></td>
						<td data-hsm-value="<?php echo esc_attr( $benchmark_max ); ?>" data-hsm-unit="ms">
							<span class="hsm-metric-value" data-hsm-value><?php echo esc_html( number_format( $benchmark_max, 2 ) ); ?></span>
							<span class="hsm-metric-unit" data-hsm-unit>ms</span>
						</td>
					</tr>
					<tr data-hsm-metric="benchmark_iterations">
						<td data-hsm-label="benchmark_iterations"><?php esc_html_e( 'Iterations', 'hypercart-server-monitor' ); ?></td>
						<td data-hsm-value="<?php echo esc_attr( $benchmark_iterations ); ?>">
							<span class="hsm-metric-value" data-hsm-value><?php echo esc_html( $benchmark_iterations ); ?></span>
						</td>
					</tr>
				</tbody>
			</table>
		<?php else : ?>
			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'No health data available yet. The first check will run within 15 minutes.', 'hypercart-server-monitor' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<!-- Health Score Chart (24h) -->
	<?php if ( ! empty( $samples ) && class_exists( 'Hypercart_Charts' ) ) : ?>
		<div class="hsm-card hsm-chart-container">
			<h2><?php esc_html_e( 'Health Score (24 Hours)', 'hypercart-server-monitor' ); ?></h2>
			<?php
			// Prepare chart data.
			$chart_points = array();
			foreach ( $samples as $sample ) {
				$ts    = isset( $sample['ts_utc'] ) ? strtotime( $sample['ts_utc'] ) : null;
				$score = $sample['score']['combined'] ?? null;

				if ( $ts && null !== $score ) {
					$chart_points[] = array(
						'x' => $ts,
						'y' => $score,
					);
				}
			}

			if ( ! empty( $chart_points ) ) {
				echo \Hypercart_Charts::render_canvas(
					array(
						'id'       => 'hsm-health-chart-frontend',
						'title'    => __( 'Health Score Over Time', 'hypercart-server-monitor' ),
						'datasets' => array(
							array(
								'key'    => 'health_score',
								'label'  => __( 'Health Score', 'hypercart-server-monitor' ),
								'color'  => '#2271b1',
								'points' => $chart_points,
							),
						),
					),
					array( 'height' => 300 )
				);
			}
			?>
		</div>
	<?php endif; ?>

	<!-- Recent Samples Table -->
	<?php if ( ! empty( $samples ) ) : ?>
		<div class="hsm-card hsm-samples-table">
			<h2><?php esc_html_e( 'Recent Samples', 'hypercart-server-monitor' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Timestamp', 'hypercart-server-monitor' ); ?></th>
						<th><?php esc_html_e( 'Score', 'hypercart-server-monitor' ); ?></th>
						<th><?php esc_html_e( 'Label', 'hypercart-server-monitor' ); ?></th>
						<th><?php esc_html_e( 'Benchmark 1 (ms)', 'hypercart-server-monitor' ); ?></th>
						<th><?php esc_html_e( 'Benchmark 2 (ms)', 'hypercart-server-monitor' ); ?></th>
						<th><?php esc_html_e( 'Benchmark 3 (ms)', 'hypercart-server-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$recent_samples = array_slice( array_reverse( $samples ), 0, 10 );
					foreach ( $recent_samples as $sample ) :
						$ts    = isset( $sample['ts_utc'] ) ? strtotime( $sample['ts_utc'] ) : null;
						$score = $sample['score'] ?? array();
						$raw   = $sample['raw'] ?? array();
						$times = $raw['benchmark']['all_times_ms'] ?? array();
						$time_1 = isset( $times[0] ) ? number_format( $times[0], 2 ) : 'N/A';
						$time_2 = isset( $times[1] ) ? number_format( $times[1], 2 ) : 'N/A';
						$time_3 = isset( $times[2] ) ? number_format( $times[2], 2 ) : 'N/A';
						?>
						<tr>
							<td><?php echo esc_html( $ts ? \Hypercart_Time::format( 'Y-m-d H:i:s', $ts ) : 'N/A' ); ?></td>
							<td><?php echo esc_html( number_format( $score['combined'] ?? 0, 1 ) ); ?></td>
							<td><?php echo esc_html( $score['label'] ?? 'Unknown' ); ?></td>
							<td><?php echo esc_html( $time_1 ); ?></td>
							<td><?php echo esc_html( $time_2 ); ?></td>
							<td><?php echo esc_html( $time_3 ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
