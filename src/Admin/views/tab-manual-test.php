<?php
/**
 * Manual Test Tab View
 *
 * @package Hypercart_Server_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="hsm-manual-test">
	<div class="hsm-card">
		<h2><?php esc_html_e( 'Manual Test Runner', 'hypercart-server-monitor' ); ?></h2>
		<p><?php esc_html_e( 'Run a synthetic PHP performance benchmark and save it to Recent Samples. This is useful for testing and debugging.', 'hypercart-server-monitor' ); ?></p>

		<button type="button" id="hsm-run-test" class="button button-primary button-large">
			<span class="dashicons dashicons-controls-play"></span>
			<?php esc_html_e( 'Run Test Now', 'hypercart-server-monitor' ); ?>
		</button>
		<p class="description">
			<?php esc_html_e( 'Manual tests share the same lock as scheduled runs; if another run is in progress, the test will be skipped.', 'hypercart-server-monitor' ); ?>
		</p>

		<div id="hsm-test-spinner" class="hsm-spinner" style="display: none;">
			<span class="spinner is-active"></span>
			<span><?php esc_html_e( 'Running test...', 'hypercart-server-monitor' ); ?></span>
		</div>

		<div id="hsm-test-results" class="hsm-test-results" style="display: none;">
			<!-- Results will be injected here via JavaScript -->
		</div>

		<div id="hsm-test-error" class="notice notice-error inline" style="display: none;">
			<p><strong><?php esc_html_e( 'Error:', 'hypercart-server-monitor' ); ?></strong> <span id="hsm-error-message"></span></p>
		</div>
	</div>

	<div class="hsm-card hsm-test-info">
		<h3><?php esc_html_e( 'About Manual Testing', 'hypercart-server-monitor' ); ?></h3>
		<p><?php esc_html_e( 'The benchmark runs 3 iterations of compute-intensive PHP tasks (math operations, string manipulation, array processing) and averages the execution time.', 'hypercart-server-monitor' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'Manual tests DO save results to the JSON file and appear in Recent Samples', 'hypercart-server-monitor' ); ?></li>
			<li><?php esc_html_e( 'Manual tests do NOT trigger email notifications', 'hypercart-server-monitor' ); ?></li>
			<li><?php esc_html_e( 'Manual tests run through the FSM state transitions', 'hypercart-server-monitor' ); ?></li>
			<li><?php esc_html_e( 'Lower execution time = better performance = higher score', 'hypercart-server-monitor' ); ?></li>
		</ul>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	/**
	 * Escape HTML to prevent XSS when building dynamic HTML strings.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped text safe for HTML insertion.
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	$('#hsm-run-test').on('click', function() {
		var $button = $(this);
		var $spinner = $('#hsm-test-spinner');
		var $results = $('#hsm-test-results');
		var $error = $('#hsm-test-error');

		// Disable button and show spinner.
		$button.prop('disabled', true);
		$spinner.show();
		$results.hide();
		$error.hide();

		// Run AJAX request.
		$.ajax({
			url: hsmAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'hsm_run_manual_test',
				nonce: hsmAdmin.nonce
			},
			success: function(response) {
				console.log('Manual test response:', response);

				$spinner.hide();
				$button.prop('disabled', false);

				if (response.success) {
					var data = response.data;
					var score = data.score;
					var rawMetrics = data.raw_metrics;
					var cronHealth = data.cron_health || {};

					console.log('Score:', score);
					console.log('Raw metrics:', rawMetrics);
					console.log('Cron health:', cronHealth);

					// Validate response data.
					if (!score || typeof score.combined === 'undefined') {
						console.error('Invalid score data:', score);
						$error.find('#hsm-error-message').text('<?php esc_html_e( 'Invalid response: score data missing', 'hypercart-server-monitor' ); ?>');
						$error.show();
						return;
					}

					if (!rawMetrics) {
						console.error('Invalid raw metrics:', rawMetrics);
						$error.find('#hsm-error-message').text('<?php esc_html_e( 'Invalid response: metrics data missing', 'hypercart-server-monitor' ); ?>');
						$error.show();
						return;
					}

					try {
						// Determine score class.
						var scoreClass = 'hsm-score-unknown';
						if (score.combined >= 90) {
							scoreClass = 'hsm-score-excellent';
						} else if (score.combined >= 75) {
							scoreClass = 'hsm-score-good';
						} else if (score.combined >= 60) {
							scoreClass = 'hsm-score-warning';
						} else {
							scoreClass = 'hsm-score-critical';
						}

						// Build results HTML.
						var html = '<div class="hsm-test-success">';
						html += '<h3><?php esc_html_e( 'Test Results', 'hypercart-server-monitor' ); ?></h3>';

						// Show warnings if any.
						if (data.warnings && data.warnings.length > 0) {
							html += '<div class="notice notice-warning inline"><ul>';
							for (var i = 0; i < data.warnings.length; i++) {
								html += '<li>' + escapeHtml(data.warnings[i]) + '</li>';
							}
							html += '</ul></div>';
						}

						html += '<div class="hsm-score-display ' + scoreClass + '">';
						html += '<div class="hsm-score-number">' + score.combined.toFixed(1) + '</div>';
						html += '<div class="hsm-score-label">' + escapeHtml(score.label) + '</div>';
						html += '</div>';
						html += '<p class="hsm-timestamp"><?php esc_html_e( 'Completed at:', 'hypercart-server-monitor' ); ?> ' + escapeHtml(data.timestamp) + '</p>';
						html += '<p><?php esc_html_e( 'Duration:', 'hypercart-server-monitor' ); ?> ' + data.duration_ms.toFixed(2) + ' ms</p>';

						// Show logging status.
						if (data.logged) {
							html += '<p class="hsm-log-status"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Test logged to Hypercart logs', 'hypercart-server-monitor' ); ?></p>';
						}

						// Extract benchmark times for unified table display.
						var allTimes = rawMetrics.benchmark && rawMetrics.benchmark.all_times_ms ? rawMetrics.benchmark.all_times_ms : [];
						var time1 = allTimes[0] ? allTimes[0].toFixed(2) : 'N/A';
						var time2 = allTimes[1] ? allTimes[1].toFixed(2) : 'N/A';
						var time3 = allTimes[2] ? allTimes[2].toFixed(2) : 'N/A';

						// Calculate total time (sum of all 3 runs).
						var totalTime = 'N/A';
						if (allTimes[0] && allTimes[1] && allTimes[2]) {
							var sum = allTimes[0] + allTimes[1] + allTimes[2];
							totalTime = sum.toFixed(2);
						}

						// Determine cron health status and class.
						var cronHealthStatus = cronHealth.status || '';
						var cronHealthLabel = cronHealthStatus ? cronHealthStatus.toUpperCase() : 'N/A';
						var cronHealthClass = (cronHealthStatus === 'healthy' || cronHealthStatus === 'unhealthy') ? cronHealthStatus : 'unknown';

						// Unified table matching dashboard/shortcode format.
						html += '<table class="hsm-table widefat striped">';
						html += '<thead><tr>';
						html += '<th><?php esc_html_e( 'Timestamp', 'hypercart-server-monitor' ); ?></th>';
						html += '<th><?php esc_html_e( 'Score', 'hypercart-server-monitor' ); ?></th>';
						html += '<th><?php esc_html_e( 'Label', 'hypercart-server-monitor' ); ?></th>';
						html += '<th><?php esc_html_e( 'Run 1 (ms)', 'hypercart-server-monitor' ); ?></th>';
						html += '<th><?php esc_html_e( 'Run 2 (ms)', 'hypercart-server-monitor' ); ?></th>';
						html += '<th><?php esc_html_e( 'Run 3 (ms)', 'hypercart-server-monitor' ); ?></th>';
						html += '<th><?php esc_html_e( 'Total (ms)', 'hypercart-server-monitor' ); ?></th>';
						html += '<th><?php esc_html_e( 'Cron Health', 'hypercart-server-monitor' ); ?></th>';
						html += '</tr></thead><tbody>';

						// Single row with current test results.
						html += '<tr>';
						html += '<td class="hsm-table-timestamp">' + escapeHtml(data.timestamp) + '</td>';
						html += '<td>' + score.combined.toFixed(1) + '</td>';
						html += '<td>' + escapeHtml(score.label) + '</td>';
						html += '<td class="hsm-table-value">' + time1 + '</td>';
						html += '<td class="hsm-table-value">' + time2 + '</td>';
						html += '<td class="hsm-table-value">' + time3 + '</td>';
						html += '<td class="hsm-table-value">' + totalTime + '</td>';
						html += '<td><span class="hsm-cron-health ' + cronHealthClass + '">' + escapeHtml(cronHealthLabel) + '</span></td>';
						html += '</tr>';

						html += '</tbody></table>';
						html += '</div>';

						$results.html(html).show();

					} catch (e) {
						console.error('Error building results HTML:', e);
						$error.find('#hsm-error-message').text('<?php esc_html_e( 'Error displaying results. Check browser console for details.', 'hypercart-server-monitor' ); ?>');
						$error.show();
					}
				} else {
					console.error('AJAX error response:', response);
					var errorMsg = response.data && response.data.message ? response.data.message : '<?php esc_html_e( 'Unknown error', 'hypercart-server-monitor' ); ?>';
					$error.find('#hsm-error-message').text(errorMsg);

					// Show if error was logged.
					if (response.data && response.data.logged) {
						$error.append('<p><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Error logged to Hypercart logs', 'hypercart-server-monitor' ); ?></p>');
					}

					$error.show();
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX request failed:', {xhr: xhr, status: status, error: error});
				$spinner.hide();
				$button.prop('disabled', false);
				$error.find('#hsm-error-message').text('<?php esc_html_e( 'AJAX Error:', 'hypercart-server-monitor' ); ?> ' + error);
				$error.show();
			}
		});
	});
});
</script>
