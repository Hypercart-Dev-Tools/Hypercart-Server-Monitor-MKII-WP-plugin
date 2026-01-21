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
