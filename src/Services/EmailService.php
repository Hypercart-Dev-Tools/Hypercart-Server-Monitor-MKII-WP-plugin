<?php
/**
 * Email Service
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Services;

/**
 * Handles email notifications for server monitoring.
 */
class EmailService {
	/**
	 * Send performance notification email.
	 *
	 * @param array $sample Health sample data (score, raw metrics, timestamp).
	 * @return bool True on success, false on failure.
	 */
	public function send_notification( $sample ) {
		// Get recipient email.
		$recipient = $this->get_recipient_email();

		// Build subject line.
		$subject = $this->build_subject( $sample );

		// Build email body.
		$body = $this->build_body( $sample );

		// Set headers for HTML email.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		// Send email.
		$sent = wp_mail( $recipient, $subject, $body, $headers );

		if ( $sent ) {
			\Hypercart_Logger::info(
				'hypercart-server-monitor',
				'Email notification sent',
				array(
					'recipient' => $recipient,
					'subject'   => $subject,
				)
			);
		} else {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Failed to send email notification',
				array(
					'recipient' => $recipient,
					'subject'   => $subject,
				)
			);
		}

		return $sent;
	}

	/**
	 * Get recipient email address.
	 *
	 * @return string Email address.
	 */
	private function get_recipient_email() {
		// TODO: Make this configurable in settings.
		return get_option( 'admin_email' );
	}

	/**
	 * Build email subject line.
	 *
	 * Format: [Server Monitor] Score: 100 (Excellent) | Benchmark: 36.5ms
	 *
	 * @param array $sample Health sample data.
	 * @return string Subject line.
	 */
	private function build_subject( $sample ) {
		$score          = $sample['score'] ?? array();
		$combined_score = $score['combined'] ?? 0;
		$label          = $score['label'] ?? 'Unknown';
		$benchmark_ms   = $score['benchmark_ms'] ?? 0;

		return sprintf(
			'[Server Monitor] Score: %.0f (%s) | Benchmark: %.1fms',
			$combined_score,
			$label,
			$benchmark_ms
		);
	}

	/**
	 * Build email body (HTML).
	 *
	 * @param array $sample Health sample data.
	 * @return string HTML email body.
	 */
	private function build_body( $sample ) {
		$score       = $sample['score'] ?? array();
		$raw_metrics = $sample['raw'] ?? array();
		$timestamp   = $sample['ts_utc'] ?? '';

		$combined_score = $score['combined'] ?? 0;
		$label          = $score['label'] ?? 'Unknown';
		$benchmark_ms   = $score['benchmark_ms'] ?? 0;

		// Get benchmark details.
		$benchmark = $raw_metrics['benchmark'] ?? array();
		$avg_time  = $benchmark['avg_time_ms'] ?? 0;
		$min_time  = $benchmark['min_time_ms'] ?? 0;
		$max_time  = $benchmark['max_time_ms'] ?? 0;
		$iterations = $benchmark['iterations'] ?? 0;

		// Get site info.
		$site_name = get_bloginfo( 'name' );
		$site_url  = get_bloginfo( 'url' );
		$admin_url = admin_url( 'admin.php?page=hypercart-server-monitor' );

		// Format timestamps.
		$utc_time   = $timestamp;
		$local_time = \Hypercart_Time::format( 'Y-m-d H:i:s', strtotime( $timestamp ) );

		// Determine score color.
		$score_color = '#d63638'; // Critical (red).
		if ( $combined_score >= 90 ) {
			$score_color = '#00a32a'; // Excellent (green).
		} elseif ( $combined_score >= 75 ) {
			$score_color = '#007cba'; // Good (blue).
		} elseif ( $combined_score >= 60 ) {
			$score_color = '#dba617'; // Warning (yellow).
		}

		// Build HTML email.
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #f0f0f1; padding: 20px; border-radius: 4px; margin-bottom: 20px; }
				.score { font-size: 48px; font-weight: bold; color: <?php echo esc_attr( $score_color ); ?>; }
				.label { font-size: 24px; color: #666; }
			.metrics { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 20px; }
			.metric-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f1; }
			.metric-row:last-child { border-bottom: none; }
			.metric-label { font-weight: 600; color: #666; }
			.metric-value { color: #333; }
			.footer { text-align: center; color: #999; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
			.button { display: inline-block; padding: 12px 24px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 4px; margin: 20px 0; }
		</style>
	</head>
	<body>
		<div class="container">
			<div class="header">
				<h1 style="margin: 0 0 10px 0;"><?php echo esc_html( $site_name ); ?></h1>
				<p style="margin: 0; color: #666;"><?php echo esc_html( $site_url ); ?></p>
			</div>

			<div style="text-align: center; margin-bottom: 30px;">
				<div class="score"><?php echo esc_html( $combined_score ); ?></div>
				<div class="label"><?php echo esc_html( $label ); ?></div>
			</div>

			<div class="metrics">
				<h2 style="margin-top: 0;">Benchmark Performance</h2>
				<div class="metric-row">
					<span class="metric-label">Average Time:</span>
					<span class="metric-value"><?php echo esc_html( number_format( $avg_time, 2 ) ); ?> ms</span>
				</div>
				<div class="metric-row">
					<span class="metric-label">Fastest Run:</span>
					<span class="metric-value"><?php echo esc_html( number_format( $min_time, 2 ) ); ?> ms</span>
				</div>
				<div class="metric-row">
					<span class="metric-label">Slowest Run:</span>
					<span class="metric-value"><?php echo esc_html( number_format( $max_time, 2 ) ); ?> ms</span>
				</div>
				<div class="metric-row">
					<span class="metric-label">Iterations:</span>
					<span class="metric-value"><?php echo esc_html( $iterations ); ?></span>
				</div>
			</div>

			<div class="metrics">
				<h2 style="margin-top: 0;">Timestamp</h2>
				<div class="metric-row">
					<span class="metric-label">UTC:</span>
					<span class="metric-value"><?php echo esc_html( $utc_time ); ?></span>
				</div>
				<div class="metric-row">
					<span class="metric-label">Local:</span>
					<span class="metric-value"><?php echo esc_html( $local_time ); ?></span>
				</div>
			</div>

			<div style="text-align: center;">
				<a href="<?php echo esc_url( $admin_url ); ?>" class="button">View Dashboard</a>
			</div>

			<div class="footer">
				<p>Hypercart Server Monitor MKII v<?php echo esc_html( HYPERCART_SERVER_MONITOR_VERSION ); ?></p>
				<p>Lower benchmark time = better performance = higher score</p>
			</div>
		</div>
	</body>
	</html>
		<?php
		return ob_get_clean();
	}
}

