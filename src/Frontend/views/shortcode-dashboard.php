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

<div class="hsm-dashboard-readonly">

	<!-- Header -->
	<div class="hsm-header">
		<div>
			<h1 class="hsm-header-title"><?php esc_html_e( 'Server Health 2026', 'hypercart-server-monitor' ); ?></h1>
			<p class="hsm-header-subtitle"><?php esc_html_e( 'Real-time performance benchmarks and monitoring.', 'hypercart-server-monitor' ); ?></p>
		</div>
		<div>
			<span class="hsm-badge">
				<span class="hsm-badge-dot"></span>
				<?php esc_html_e( 'Read-only view', 'hypercart-server-monitor' ); ?>
			</span>
		</div>
	</div>

	<!-- Noindex Warning -->
	<?php if ( isset( $is_noindex_enabled ) && $is_noindex_enabled && current_user_can( 'manage_options' ) ) : ?>
		<div class="hsm-notice hsm-notice-warning">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hsm-notice-icon">
				<circle cx="12" cy="12" r="10"/>
				<line x1="12" x2="12" y1="8" y2="12"/>
				<line x1="12" x2="12.01" y1="16" y2="16"/>
			</svg>
			<div class="hsm-notice-content">
				<h5><?php esc_html_e( 'Search Engine Indexing Disabled', 'hypercart-server-monitor' ); ?></h5>
				<p>
					<?php
					printf(
						/* translators: %s: code snippet */
						esc_html__( 'This page is not indexed by search engines. Change this by adding %s to the shortcode.', 'hypercart-server-monitor' ),
						'<code>noindex="false"</code>'
					);
					?>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<!-- Timezone Notice -->
	<div class="hsm-notice hsm-notice-info">
		<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hsm-notice-icon">
			<circle cx="12" cy="12" r="10"/>
			<line x1="12" x2="12" y1="16" y2="12"/>
			<line x1="12" x2="12.01" y1="8" y2="8"/>
		</svg>
		<div class="hsm-notice-content">
			<p>
				<?php
				$timezone_string = wp_timezone_string();
				printf(
					/* translators: %s: timezone name */
					esc_html__( 'All timestamps are displayed in UTC (Coordinated Universal Time). Hover over any timestamp to see your local time (%s).', 'hypercart-server-monitor' ),
					esc_html( $timezone_string )
				);
				?>
			</p>
		</div>
	</div>

	<!-- Current Health Score & Metrics Grid -->
	<?php if ( $latest_sample ) : ?>
		<?php
		$score       = $latest_sample['score'] ?? array();
		$combined    = $score['combined'] ?? 0;
		$label       = $score['label'] ?? 'Unknown';
		$timestamp   = $latest_sample['ts_utc'] ?? '';
		$raw_metrics = $latest_sample['raw'] ?? array();

		// Determine score class.
		$score_class = 'unknown';
		if ( $combined >= 90 ) {
			$score_class = 'excellent';
		} elseif ( $combined >= 75 ) {
			$score_class = 'good';
		} elseif ( $combined >= 60 ) {
			$score_class = 'warning';
		} else {
			$score_class = 'critical';
		}

		$benchmark_avg        = (float) ( $raw_metrics['benchmark']['avg_time_ms'] ?? 0 );
		$benchmark_min        = (float) ( $raw_metrics['benchmark']['min_time_ms'] ?? 0 );
		$benchmark_max        = (float) ( $raw_metrics['benchmark']['max_time_ms'] ?? 0 );
		$benchmark_iterations = (int) ( $raw_metrics['benchmark']['iterations'] ?? 0 );
		?>

		<div class="hsm-grid hsm-grid-2">

			<!-- Current Performance Score Card -->
			<div class="hsm-card">
				<div class="hsm-card-header">
					<h3 class="hsm-card-title"><?php esc_html_e( 'Current Performance Score', 'hypercart-server-monitor' ); ?></h3>
					<p class="hsm-card-description"><?php esc_html_e( 'Based on PHP benchmark execution time (lower time = higher score)', 'hypercart-server-monitor' ); ?></p>
				</div>
				<div class="hsm-card-content">
					<div class="hsm-score-container">
						<span class="hsm-score-number <?php echo esc_attr( $score_class ); ?>"><?php echo esc_html( number_format( $combined, 1 ) ); ?></span>
						<div class="hsm-score-badge <?php echo esc_attr( $score_class ); ?>">
							<?php echo esc_html( strtoupper( $label ) ); ?>
						</div>
					</div>
					<div class="hsm-timestamp">
						<?php
						$ts_unix    = strtotime( $timestamp );
						$utc_time   = gmdate( 'Y-m-d H:i:s', $ts_unix );
						$local_time = \Hypercart_Time::format( 'Y-m-d H:i:s', $ts_unix );
						$local_tz   = wp_timezone_string();
						?>
						<?php esc_html_e( 'Last updated:', 'hypercart-server-monitor' ); ?>
						<span class="hsm-timestamp-value hsm-has-tooltip"
							data-hsm-utc="<?php echo esc_attr( $utc_time ); ?>"
							data-hsm-local="<?php echo esc_attr( $local_time ); ?>"
							data-hsm-timezone="<?php echo esc_attr( $local_tz ); ?>">
							<?php echo esc_html( $utc_time ); ?> UTC
						</span>
					</div>
				</div>
			</div>

			<!-- Benchmark Metrics Card -->
			<div class="hsm-card">
				<div class="hsm-card-header">
					<h3 class="hsm-card-title"><?php esc_html_e( 'Benchmark Metrics', 'hypercart-server-monitor' ); ?></h3>
					<p class="hsm-card-description"><?php esc_html_e( 'Detailed breakdown of the current run execution.', 'hypercart-server-monitor' ); ?></p>
				</div>
				<div class="hsm-card-content">
					<div class="hsm-metrics-list">

						<div class="hsm-metric-item">
							<div class="hsm-metric-label">
								<p class="hsm-metric-name"><?php esc_html_e( 'Benchmark Time (avg)', 'hypercart-server-monitor' ); ?></p>
								<p class="hsm-metric-hint">↓ <?php esc_html_e( 'lower is better', 'hypercart-server-monitor' ); ?></p>
							</div>
							<div class="hsm-metric-value large">
								<?php echo esc_html( number_format( $benchmark_avg, 2 ) ); ?> <span class="hsm-metric-unit">ms</span>
							</div>
						</div>

						<div class="hsm-metric-item">
							<div class="hsm-metric-label">
								<p class="hsm-metric-name"><?php esc_html_e( 'Fastest Run', 'hypercart-server-monitor' ); ?></p>
							</div>
							<div class="hsm-metric-value medium fastest">
								<?php echo esc_html( number_format( $benchmark_min, 2 ) ); ?> ms
							</div>
						</div>

						<div class="hsm-metric-item">
							<div class="hsm-metric-label">
								<p class="hsm-metric-name"><?php esc_html_e( 'Slowest Run', 'hypercart-server-monitor' ); ?></p>
							</div>
							<div class="hsm-metric-value medium slowest">
								<?php echo esc_html( number_format( $benchmark_max, 2 ) ); ?> ms
							</div>
						</div>

						<div class="hsm-metric-item">
							<div class="hsm-metric-label">
								<p class="hsm-metric-name"><?php esc_html_e( 'Iterations', 'hypercart-server-monitor' ); ?></p>
							</div>
							<div class="hsm-metric-value medium">
								<?php echo esc_html( $benchmark_iterations ); ?>
							</div>
						</div>

					</div>
				</div>
			</div>

		</div>

	<?php else : ?>
		<div class="hsm-notice hsm-notice-info">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hsm-notice-icon">
				<circle cx="12" cy="12" r="10"/>
				<line x1="12" x2="12" y1="16" y2="12"/>
				<line x1="12" x2="12.01" y1="8" y2="8"/>
			</svg>
			<div class="hsm-notice-content">
				<p><?php esc_html_e( 'No health data available yet. The first check will run within 15 minutes.', 'hypercart-server-monitor' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<!-- Health Score Chart (24h) -->
	<?php if ( ! empty( $samples ) && class_exists( 'Hypercart_Charts' ) ) : ?>
		<div class="hsm-card">
			<div class="hsm-card-header">
				<h3 class="hsm-card-title"><?php esc_html_e( 'Health Score Trend', 'hypercart-server-monitor' ); ?></h3>
				<p class="hsm-card-description"><?php esc_html_e( 'Performance stability over the last 24 hours.', 'hypercart-server-monitor' ); ?></p>
			</div>
			<div class="hsm-card-content">
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
									'color'  => '#10b981',
									'points' => $chart_points,
								),
							),
						),
						array( 'height' => 200 )
					);
				}
				?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Recent Samples Table -->
	<?php if ( ! empty( $samples ) ) : ?>
		<div class="hsm-card">
			<div class="hsm-card-header" style="border-bottom: 1px solid #f1f5f9;">
				<h3 class="hsm-card-title"><?php esc_html_e( 'Recent Samples', 'hypercart-server-monitor' ); ?></h3>
				<p class="hsm-card-description"><?php esc_html_e( 'A log of the most recent benchmark executions. Timestamps shown in UTC — hover to see local time.', 'hypercart-server-monitor' ); ?></p>
			</div>
			<div class="hsm-table-wrapper">
				<table class="hsm-table" data-hsm-timezone="UTC">
					<thead>
						<tr>
							<th data-hsm-column="timestamp">
								<?php esc_html_e( 'Timestamp (UTC)', 'hypercart-server-monitor' ); ?>
							</th>
							<th><?php esc_html_e( 'Score', 'hypercart-server-monitor' ); ?></th>
							<th><?php esc_html_e( 'Label', 'hypercart-server-monitor' ); ?></th>
							<th class="text-right"><?php esc_html_e( 'Benchmark 1', 'hypercart-server-monitor' ); ?></th>
							<th class="text-right"><?php esc_html_e( 'Benchmark 2', 'hypercart-server-monitor' ); ?></th>
							<th class="text-right"><?php esc_html_e( 'Benchmark 3', 'hypercart-server-monitor' ); ?></th>
							<th><?php esc_html_e( 'Cron Health', 'hypercart-server-monitor' ); ?></th>
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

							// Cron health snapshot captured at run time (if available).
							$cron_health_meta   = $sample['meta']['cron_health'] ?? array();
							$cron_health_status = is_array( $cron_health_meta ) ? (string) ( $cron_health_meta['status'] ?? '' ) : '';
							$cron_health_label  = '' !== $cron_health_status ? strtoupper( $cron_health_status ) : 'N/A';
							$cron_health_class  = in_array( $cron_health_status, array( 'healthy', 'unhealthy' ), true ) ? $cron_health_status : 'unknown';

							// Determine score class for table row
							$row_score = $score['combined'] ?? 0;
							$row_score_class = 'unknown';
							if ( $row_score >= 90 ) {
								$row_score_class = 'excellent';
							} elseif ( $row_score >= 75 ) {
								$row_score_class = 'good';
							} elseif ( $row_score >= 60 ) {
								$row_score_class = 'warning';
							} else {
								$row_score_class = 'critical';
							}

							// Format timestamps for display and tooltip
							if ( $ts ) {
								$utc_time   = gmdate( 'Y-m-d H:i:s', $ts );
								$local_time = \Hypercart_Time::format( 'Y-m-d H:i:s', $ts );
								$local_tz   = wp_timezone_string();
							}
							?>
							<tr>
								<td class="hsm-table-timestamp hsm-has-tooltip"
									data-hsm-utc="<?php echo esc_attr( $ts ? $utc_time : 'N/A' ); ?>"
									data-hsm-local="<?php echo esc_attr( $ts ? $local_time : 'N/A' ); ?>"
									data-hsm-timezone="<?php echo esc_attr( $ts ? $local_tz : '' ); ?>">
									<?php echo esc_html( $ts ? $utc_time : 'N/A' ); ?>
								</td>
								<td class="hsm-table-score <?php echo esc_attr( $row_score_class ); ?>"><?php echo esc_html( number_format( $row_score, 1 ) ); ?></td>
								<td>
									<span class="hsm-table-label"><?php echo esc_html( $score['label'] ?? 'Unknown' ); ?></span>
								</td>
								<td class="hsm-table-value text-right"><?php echo esc_html( $time_1 ); ?> ms</td>
								<td class="hsm-table-value text-right"><?php echo esc_html( $time_2 ); ?> ms</td>
								<td class="hsm-table-value text-right"><?php echo esc_html( $time_3 ); ?> ms</td>
								<td class="hsm-table-cron-health <?php echo esc_attr( $cron_health_class ); ?>"><?php echo esc_html( $cron_health_label ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>

</div>
