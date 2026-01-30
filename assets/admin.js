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
					timeout: 10000, // 10 seconds
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
						if (status === 'timeout') {
							$statusCell.html('<span class="hsm-status-error">✗ Request timed out after 10s</span>');
						} else {
							$statusCell.html('<span class="hsm-status-error">✗ Error checking health</span> <span class="description">(' + error + ')</span>');
						}
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

		// Helper function to copy text to clipboard
		function copyToClipboard(text, $button, successText, originalText) {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				// Modern async Clipboard API
				navigator.clipboard.writeText(text).then(function() {
					$button.text(successText);
					setTimeout(function() {
						$button.text(originalText);
					}, 2000);
				}).catch(function(err) {
					// Fallback to execCommand on error
					fallbackCopy(text, $button, successText, originalText);
				});
			} else {
				// Fallback for older browsers
				fallbackCopy(text, $button, successText, originalText);
			}
		}

		function fallbackCopy(text, $button, successText, originalText) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			try {
				document.execCommand('copy');
				$button.text(successText);
				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			} catch (err) {
				console.error('Copy failed:', err);
			}
			$temp.remove();
		}

		// Copy endpoint URL button
		$('#hsm-copy-endpoint').on('click', function() {
			var $button = $(this);
			var url = $button.prev('code').text();
			var originalText = $button.text();
			copyToClipboard(url, $button, 'Copied!', originalText);
		});

		// Copy explanation button
		$('#hsm-copy-explanation').on('click', function() {
			var $button = $(this);
			var $explanationDiv = $('#hsm-health-explanation');
			var explanationText = $explanationDiv.text().trim();
			var originalHtml = $button.html();
			var successHtml = '<span class="dashicons dashicons-yes" style="font-size: 16px; vertical-align: middle;"></span> Copied!';

			if (navigator.clipboard && navigator.clipboard.writeText) {
				// Modern async Clipboard API
				navigator.clipboard.writeText(explanationText).then(function() {
					$button.html(successHtml);
					setTimeout(function() {
						$button.html(originalHtml);
					}, 2000);
				}).catch(function(err) {
					// Fallback to execCommand on error
					fallbackCopyHtml(explanationText, $button, successHtml, originalHtml);
				});
			} else {
				// Fallback for older browsers
				fallbackCopyHtml(explanationText, $button, successHtml, originalHtml);
			}
		});

		function fallbackCopyHtml(text, $button, successHtml, originalHtml) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			try {
				document.execCommand('copy');
				$button.html(successHtml);
				setTimeout(function() {
					$button.html(originalHtml);
				}, 2000);
			} catch (err) {
				console.error('Copy failed:', err);
			}
			$temp.remove();
		}

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
					timeout: 15000, // 15 seconds for self-test
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
						if (status === 'timeout') {
							$error.find('.hsm-breaker-error-message').text('Request timed out after 15s');
						} else {
							$error.find('.hsm-breaker-error-message').text('AJAX Error: ' + error);
						}
						$error.show();
					}
				});
			});
		}
	});

})(jQuery);
