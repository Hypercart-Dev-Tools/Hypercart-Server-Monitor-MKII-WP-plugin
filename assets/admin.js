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

		// Live preview update function.
		function updateLivePreview() {
			var timestampSize = $('#timestamp_size').val() + 'px';
			var timestampWeight = $('#timestamp_weight').val();
			var timestampColor = $('#timestamp_color').val();
			var benchmarkSize = $('#benchmark_size').val() + 'px';
			var benchmarkWeight = $('#benchmark_weight').val();
			var benchmarkColor = $('#benchmark_color').val();
			var healthSize = $('#health_size').val() + 'px';
			var healthWeight = $('#health_weight').val();
			var healthHealthy = $('#health_healthy').val();
			var healthUnhealthy = $('#health_unhealthy').val();

			// Apply to preview table.
			$('#hsm-font-preview-table .hsm-table-timestamp').css({
				'font-size': timestampSize,
				'font-weight': timestampWeight,
				'color': timestampColor
			});
			$('#hsm-font-preview-table .hsm-table-value').css({
				'font-size': benchmarkSize,
				'font-weight': benchmarkWeight,
				'color': benchmarkColor
			});
			$('#hsm-font-preview-table .hsm-cron-health, #hsm-font-preview-table .hsm-table-cron-health').css({
				'font-size': healthSize,
				'font-weight': healthWeight
			});
			$('#hsm-font-preview-table .hsm-cron-health.healthy, #hsm-font-preview-table .hsm-table-cron-health.healthy').css({
				'color': healthHealthy
			});
			$('#hsm-font-preview-table .hsm-cron-health.unhealthy, #hsm-font-preview-table .hsm-table-cron-health.unhealthy').css({
				'color': healthUnhealthy
			});
		}

		// Initialize color pickers on settings page.
		if ($('.hsm-color-picker').length) {
			$('.hsm-color-picker').wpColorPicker({
				change: function(event, ui) {
					updateLivePreview();
				}
			});

			// Initial preview update on page load
			setTimeout(function() {
				updateLivePreview();
			}, 100);
		}

		// Update preview on input change.
		$('#timestamp_size, #timestamp_weight, #benchmark_size, #benchmark_weight, #health_size, #health_weight').on('input change', function() {
			updateLivePreview();
		});

		// Reset to defaults button.
		$('#hsm-reset-font-settings').on('click', function(e) {
			e.preventDefault();
			if (confirm('Reset all font settings to defaults?')) {
				$('#timestamp_size').val('14');
				$('#timestamp_weight').val('400');
				$('#timestamp_color').val('#000000').wpColorPicker('color', '#000000');
				$('#benchmark_size').val('14');
				$('#benchmark_weight').val('400');
				$('#benchmark_color').val('#000000').wpColorPicker('color', '#000000');
				$('#health_size').val('12');
				$('#health_weight').val('600');
				$('#health_healthy').val('#059669').wpColorPicker('color', '#059669');
				$('#health_unhealthy').val('#dc2626').wpColorPicker('color', '#dc2626');
				updateLivePreview();
			}
		});

			// Cron Health Check - Auto-load on page load
			function checkCronHealth() {
				var $statusCell = $('#hsm-cron-health-status');
				if ($statusCell.length) {
					$.ajax({
						url: hsmAdmin.restUrl + 'cron-health/v1/status',
						type: 'GET',
						timeout: 10000, // 10 seconds
						success: function(response) {
							$statusCell.empty();
							if (response.status === 'healthy') {
								$statusCell.append($('<span>').addClass('hsm-status-ok').text('✓ Healthy'));
								if (response.last_run) {
									$statusCell.append(' ');
									$statusCell.append($('<span>').addClass('description').text('(Last run: ' + response.last_run + ')'));
								}
							} else {
								$statusCell.append($('<span>').addClass('hsm-status-error').text('✗ Unhealthy'));
								$statusCell.append(' ');
								$statusCell.append($('<span>').addClass('description').text('Cron may not be running properly'));
							}
						},
						error: function(xhr, status, error) {
							if (status === 'timeout') {
								$statusCell.empty().append($('<span>').addClass('hsm-status-error').text('✗ Request timed out after 10s'));
							} else {
								$statusCell.empty();
								$statusCell.append($('<span>').addClass('hsm-status-error').text('✗ Error checking health'));
								$statusCell.append(' ');
								$statusCell.append($('<span>').addClass('description').text('(' + error + ')'));
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

	// Email notifications toggle
	$('#hsm-email-notifications-toggle').on('change', function() {
		var $toggle = $(this);
		var $status = $('#hsm-email-toggle-status');
		var $feedback = $('#hsm-email-toggle-feedback');
		var enabled = $toggle.is(':checked') ? '1' : '0';
		var nonce = $toggle.data('nonce');

		// Disable toggle during save
		$toggle.prop('disabled', true);
		$feedback.hide();

		// Save via AJAX
		$.ajax({
			url: hsmAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'hsm_toggle_email_notifications',
				nonce: nonce,
				enabled: enabled
			},
			success: function(response) {
				$toggle.prop('disabled', false);

					if (response.success) {
						// Update status text
						$status.text(enabled === '1' ? 'Enabled' : 'Disabled');

						// Show success feedback
						$feedback
							.removeClass('error')
							.addClass('success')
							.empty()
							.append($('<span>').addClass('dashicons dashicons-yes'))
							.append(' ')
							.append(document.createTextNode(response.data.message || 'Saved.'))
							.fadeIn()
							.delay(3000)
							.fadeOut();
					} else {
						// Revert toggle on error
						$toggle.prop('checked', enabled === '0');

						// Show error feedback
						$feedback
							.removeClass('success')
							.addClass('error')
							.empty()
							.append($('<span>').addClass('dashicons dashicons-no'))
							.append(' ')
							.append(document.createTextNode((response.data && response.data.message) ? response.data.message : 'Failed to save setting'))
							.fadeIn();
					}
				},
				error: function(xhr, status, error) {
					$toggle.prop('disabled', false);

				// Revert toggle on error
				$toggle.prop('checked', enabled === '0');

					// Show error feedback
					$feedback
						.removeClass('success')
						.addClass('error')
						.empty()
						.append($('<span>').addClass('dashicons dashicons-no'))
						.append(' ')
						.append(document.createTextNode('AJAX error: ' + error))
						.fadeIn();
				}
			});
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
								$results.empty();
								if (steps.length) {
									var $list = $('<ul>');
									for (var i = 0; i < steps.length; i++) {
										$list.append($('<li>').text(steps[i] && steps[i].step ? steps[i].step : ''));
									}
									$results.append($list);
								}
								if (response.data && response.data.message) {
									$results.append($('<p>').addClass('hsm-status-ok').text(response.data.message));
								}
								$results.show();
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
