<?php
/**
 * Email Tab View
 *
 * @package Hypercart_Server_Monitor
 * @var string $recipient Email recipient address.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="hsm-email-tab">
	<div class="hsm-card">
		<h2><?php esc_html_e( 'Email Notifications', 'hypercart-server-monitor' ); ?></h2>
		<p><?php esc_html_e( 'Email notifications are sent automatically every 15 minutes after each benchmark run.', 'hypercart-server-monitor' ); ?></p>
		
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Recipient', 'hypercart-server-monitor' ); ?></th>
					<td><?php echo esc_html( $recipient ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Frequency', 'hypercart-server-monitor' ); ?></th>
					<td><?php esc_html_e( 'Every 15 minutes (with cron)', 'hypercart-server-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Subject Format', 'hypercart-server-monitor' ); ?></th>
					<td><code>[Server Monitor] Score: 100 (Excellent) | Benchmark: 36.5ms</code></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="hsm-card">
		<h2><?php esc_html_e( 'Send Test Email', 'hypercart-server-monitor' ); ?></h2>
		<p><?php esc_html_e( 'Send a test email using the most recently saved benchmark data. This does NOT run a new benchmark.', 'hypercart-server-monitor' ); ?></p>

		<button type="button" id="hsm-send-test-email" class="button button-primary button-large">
			<span class="dashicons dashicons-email"></span>
			<?php esc_html_e( 'Send Test Email', 'hypercart-server-monitor' ); ?>
		</button>

		<div id="hsm-email-spinner" class="hsm-spinner" style="display: none;">
			<span class="spinner is-active"></span>
			<span><?php esc_html_e( 'Sending email...', 'hypercart-server-monitor' ); ?></span>
		</div>

		<div id="hsm-email-success" class="notice notice-success inline" style="display: none;">
			<p><strong><?php esc_html_e( 'Success!', 'hypercart-server-monitor' ); ?></strong> <span id="hsm-email-success-message"></span></p>
		</div>

		<div id="hsm-email-error" class="notice notice-error inline" style="display: none;">
			<p><strong><?php esc_html_e( 'Error:', 'hypercart-server-monitor' ); ?></strong> <span id="hsm-email-error-message"></span></p>
		</div>
	</div>

	<div class="hsm-card hsm-test-info">
		<h3><?php esc_html_e( 'About Email Notifications', 'hypercart-server-monitor' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Emails are sent automatically after each benchmark run', 'hypercart-server-monitor' ); ?></li>
			<li><?php esc_html_e( 'Subject line includes score and benchmark time for quick scanning', 'hypercart-server-monitor' ); ?></li>
			<li><?php esc_html_e( 'Email body includes full benchmark details and link to dashboard', 'hypercart-server-monitor' ); ?></li>
			<li><?php esc_html_e( 'Test emails use the most recent saved data (no new benchmark is run)', 'hypercart-server-monitor' ); ?></li>
		</ul>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#hsm-send-test-email').on('click', function() {
		var $button = $(this);
		var $spinner = $('#hsm-email-spinner');
		var $success = $('#hsm-email-success');
		var $error = $('#hsm-email-error');

		// Disable button and show spinner.
		$button.prop('disabled', true);
		$spinner.show();
		$success.hide();
		$error.hide();

		// Run AJAX request.
		$.ajax({
			url: hsmAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'hsm_send_test_email',
				nonce: hsmAdmin.emailNonce
			},
			success: function(response) {
				$spinner.hide();
				$button.prop('disabled', false);

				if (response.success) {
					var message = response.data.message + ' (Sent to: ' + response.data.recipient + ')';
					$success.find('#hsm-email-success-message').text(message);
					$success.show();
				} else {
					$error.find('#hsm-email-error-message').text(response.data.message || 'Unknown error');
					$error.show();
				}
			},
			error: function(xhr, status, error) {
				$spinner.hide();
				$button.prop('disabled', false);
				$error.find('#hsm-email-error-message').text('AJAX error: ' + error);
				$error.show();
			}
		});
	});
});
</script>

<style>
.hsm-email-tab .hsm-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.hsm-email-tab .hsm-card h2 {
	margin-top: 0;
}

.hsm-email-tab .hsm-spinner {
	margin-top: 15px;
	display: flex;
	align-items: center;
	gap: 10px;
}

.hsm-email-tab .button-large {
	height: auto;
	padding: 12px 24px;
	font-size: 14px;
}

.hsm-email-tab .button-large .dashicons {
	margin-right: 8px;
}

.hsm-email-tab .notice {
	margin-top: 15px;
}
</style>

