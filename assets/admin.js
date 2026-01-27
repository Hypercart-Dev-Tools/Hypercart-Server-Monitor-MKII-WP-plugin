/**
 * Admin JavaScript for Hypercart Server Monitor
 */

(function($) {
	'use strict';

	/**
	 * Initialize admin functionality.
	 */
	$(document).ready(function() {
		// Admin is ready.
		console.log('Hypercart Server Monitor Admin loaded');

		// Cron Health Check - Auto-load on page load
		function checkCronHealth() {
			var $statusCell = $('#hsm-cron-health-status');
			if ($statusCell.length) {
				$.ajax({
					url: hsmAdmin.restUrl + 'cron-health/v1/status',
					type: 'GET',
					success: function(response) {
						var html = '';
						if (response.status === 'healthy') {
							html = '<span class="hsm-status-ok">✓ Healthy</span>';
							if (response.last_run) {
								html += ' <span class="description">(Last run: ' + response.last_run + ')</span>';
							}
						} else {
							html = '<span class="hsm-status-error">✗ Unhealthy</span>';
							html += ' <span class="description">Cron may not be running properly</span>';
						}
						$statusCell.html(html);
					},
					error: function(xhr, status, error) {
						$statusCell.html('<span class="hsm-status-error">✗ Error checking health</span> <span class="description">(' + error + ')</span>');
					}
				});
			}
		}

		// Check cron health on page load
		checkCronHealth();

		// Test Cron Health button
		$('#hsm-test-cron-health').on('click', function() {
			checkCronHealth();
		});

		// Copy endpoint URL button
		$('#hsm-copy-endpoint').on('click', function() {
			var $button = $(this);
			var url = $button.prev('code').text();

			// Create temporary input to copy text
			var $temp = $('<input>');
			$('body').append($temp);
			$temp.val(url).select();
			document.execCommand('copy');
			$temp.remove();

			// Show feedback
			var originalText = $button.text();
			$button.text('Copied!');
			setTimeout(function() {
				$button.text(originalText);
			}, 2000);
		});

	// Copy explanation button
	$('#hsm-copy-explanation').on('click', function() {
		var $button = $(this);
		var $explanationDiv = $('#hsm-health-explanation');

		// Extract plain text from the explanation div
		var explanationText = $explanationDiv.text().trim();

		// Create temporary textarea to copy text
		var $temp = $('<textarea>');
		$('body').append($temp);
		$temp.val(explanationText).select();
		document.execCommand('copy');
		$temp.remove();

		// Show feedback
		var originalHtml = $button.html();
		$button.html('<span class="dashicons dashicons-yes" style="font-size: 16px; vertical-align: middle;"></span> Copied!');
		setTimeout(function() {
			$button.html(originalHtml);
		}, 2000);
	});

		var $breakerButton = $('#hsm-run-breaker-test');
		if ($breakerButton.length) {
			$breakerButton.on('click', function() {
				var $spinner = $('#hsm-breaker-test-spinner');
				var $results = $('#hsm-breaker-test-results');
				var $error = $('#hsm-breaker-test-error');

				$breakerButton.prop('disabled', true);
				$spinner.show();
				$results.hide().empty();
				$error.hide();

				$.ajax({
					url: hsmAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'hsm_run_breaker_self_test',
						nonce: hsmAdmin.debugNonce
					},
					success: function(response) {
						$spinner.hide();
						$breakerButton.prop('disabled', false);

						if (response.success) {
							var steps = response.data.steps || [];
							var html = '<ul>';
							for (var i = 0; i < steps.length; i++) {
								html += '<li>' + steps[i].step + '</li>';
							}
							html += '</ul>';
							if (response.data.message) {
								html += '<p class="hsm-status-ok">' + response.data.message + '</p>';
							}
							$results.html(html).show();
						} else {
							var errorMsg = response.data && response.data.message ? response.data.message : 'Self test failed.';
							$error.find('.hsm-breaker-error-message').text(errorMsg);
							$error.show();
						}
					},
					error: function(xhr, status, error) {
						$spinner.hide();
						$breakerButton.prop('disabled', false);
						$error.find('.hsm-breaker-error-message').text('AJAX Error: ' + error);
						$error.show();
					}
				});
			});
		}
	});

})(jQuery);
