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
		<p><?php esc_html_e( 'Configure automatic email notifications sent after each benchmark run.', 'hypercart-server-monitor' ); ?></p>

		<?php
		// Get current email notifications setting (default: enabled).
		$email_enabled = get_option( \Hypercart_Server_Monitor\Plugin::OPTION_EMAIL_NOTIFICATIONS_ENABLED, '1' );
		$is_enabled    = '1' === $email_enabled;
		?>

		<!-- Email Notifications Toggle -->
		<div class="hsm-email-toggle-section">
			<div class="hsm-email-toggle-row">
				<div class="hsm-email-toggle-label">
					<strong><?php esc_html_e( 'Enable Email Notifications', 'hypercart-server-monitor' ); ?></strong>
					<p class="description">
						<?php esc_html_e( 'Automatically send email alerts after each benchmark run (every 15 minutes).', 'hypercart-server-monitor' ); ?>
					</p>
				</div>
				<div class="hsm-email-toggle-control">
					<label class="hsm-toggle-switch">
						<input type="checkbox"
						       id="hsm-email-notifications-toggle"
						       <?php checked( $is_enabled ); ?>
						       data-nonce="<?php echo esc_attr( wp_create_nonce( 'hsm_toggle_email_notifications' ) ); ?>">
						<span class="hsm-toggle-slider"></span>
					</label>
					<span class="hsm-toggle-status" id="hsm-email-toggle-status">
						<?php echo $is_enabled ? esc_html__( 'Enabled', 'hypercart-server-monitor' ) : esc_html__( 'Disabled', 'hypercart-server-monitor' ); ?>
					</span>
				</div>
			</div>
			<div id="hsm-email-toggle-feedback" class="hsm-toggle-feedback" style="display: none;"></div>
		</div>

		<table class="widefat" style="margin-top: 20px;">
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

/* Email Toggle Section */
.hsm-email-toggle-section {
	background: #f9f9f9;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.hsm-email-toggle-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 20px;
}

.hsm-email-toggle-label {
	flex: 1;
}

.hsm-email-toggle-label strong {
	font-size: 14px;
	color: #23282d;
}

.hsm-email-toggle-label .description {
	margin: 5px 0 0 0;
	color: #646970;
}

.hsm-email-toggle-control {
	display: flex;
	align-items: center;
	gap: 12px;
}

/* Toggle Switch */
.hsm-toggle-switch {
	position: relative;
	display: inline-block;
	width: 50px;
	height: 26px;
	margin: 0;
}

.hsm-toggle-switch input {
	opacity: 0;
	width: 0;
	height: 0;
}

.hsm-toggle-slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: #ccc;
	transition: 0.3s;
	border-radius: 26px;
}

.hsm-toggle-slider:before {
	position: absolute;
	content: "";
	height: 20px;
	width: 20px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: 0.3s;
	border-radius: 50%;
}

input:checked + .hsm-toggle-slider {
	background-color: #2271b1;
}

input:focus + .hsm-toggle-slider {
	box-shadow: 0 0 1px #2271b1;
}

input:checked + .hsm-toggle-slider:before {
	transform: translateX(24px);
}

input:disabled + .hsm-toggle-slider {
	opacity: 0.5;
	cursor: not-allowed;
}

.hsm-toggle-status {
	font-weight: 600;
	color: #646970;
	min-width: 70px;
}

.hsm-toggle-feedback {
	margin-top: 15px;
	padding: 10px 15px;
	border-radius: 4px;
	display: flex;
	align-items: center;
	gap: 8px;
}

.hsm-toggle-feedback.success {
	background: #d7f0d7;
	border: 1px solid #00a32a;
	color: #00a32a;
}

.hsm-toggle-feedback.error {
	background: #f9e2e2;
	border: 1px solid #d63638;
	color: #d63638;
}

.hsm-toggle-feedback .dashicons {
	font-size: 18px;
}
</style>

