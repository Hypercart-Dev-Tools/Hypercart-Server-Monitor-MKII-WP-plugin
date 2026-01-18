<?php
/**
 * Debug Tab View
 *
 * @package Hypercart_Server_Monitor
 * @var array $state_data  FSM state data.
 * @var array $file_status File status information.
 * @var array $lock_status Lock status information.
 * @var array $cron_status Cron status information.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="hsm-debug">
	<!-- FSM State -->
	<div class="hsm-card">
		<h2><?php esc_html_e( 'FSM State', 'hypercart-server-monitor' ); ?></h2>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Current State', 'hypercart-server-monitor' ); ?></th>
					<td><code><?php echo esc_html( $state_data['state'] ?? 'unknown' ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last Updated', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php
						if ( ! empty( $state_data['updated_utc'] ) ) {
							echo esc_html( \Hypercart_Time::format( 'Y-m-d H:i:s', $state_data['updated_utc'] ) );
						} else {
							esc_html_e( 'N/A', 'hypercart-server-monitor' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Failure Count', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php
						$failure_count = $state_data['failure_count'] ?? 0;
						$class = $failure_count >= 3 ? 'hsm-status-error' : 'hsm-status-ok';
						?>
						<span class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $failure_count ); ?></span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last Error', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php
						if ( ! empty( $state_data['last_error'] ) ) {
							echo '<code>' . esc_html( $state_data['last_error'] ) . '</code>';
						} else {
							esc_html_e( 'None', 'hypercart-server-monitor' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last Run', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php
						if ( ! empty( $state_data['last_run_utc'] ) ) {
							echo esc_html( \Hypercart_Time::format( 'Y-m-d H:i:s', $state_data['last_run_utc'] ) );
						} else {
							esc_html_e( 'Never', 'hypercart-server-monitor' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last Duration', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php
						if ( ! empty( $state_data['last_duration_ms'] ) ) {
							echo esc_html( number_format( $state_data['last_duration_ms'], 2 ) ) . ' ms';
						} else {
							esc_html_e( 'N/A', 'hypercart-server-monitor' );
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Cron Status -->
	<div class="hsm-card">
		<h2><?php esc_html_e( 'Cron Status', 'hypercart-server-monitor' ); ?></h2>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Scheduled', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php if ( $cron_status['scheduled'] ) : ?>
							<span class="hsm-status-ok"><?php esc_html_e( 'Yes', 'hypercart-server-monitor' ); ?></span>
						<?php else : ?>
							<span class="hsm-status-error"><?php esc_html_e( 'No', 'hypercart-server-monitor' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Next Run', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php
						if ( ! empty( $cron_status['next_run_local'] ) ) {
							echo esc_html( $cron_status['next_run_local'] );
						} else {
							esc_html_e( 'Not scheduled', 'hypercart-server-monitor' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Hook Name', 'hypercart-server-monitor' ); ?></th>
					<td><code><?php echo esc_html( $cron_status['hook'] ?? 'N/A' ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Interval', 'hypercart-server-monitor' ); ?></th>
					<td><code><?php echo esc_html( $cron_status['interval'] ?? 'N/A' ); ?></code></td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! $cron_status['scheduled'] ) : ?>
			<div style="margin-top: 15px;">
				<button type="button" id="hsm-schedule-cron" class="button button-primary">
					<span class="dashicons dashicons-clock"></span>
					<?php esc_html_e( 'Schedule Cron Now', 'hypercart-server-monitor' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'Manually schedule the cron job if it was not scheduled during plugin activation.', 'hypercart-server-monitor' ); ?>
				</p>

				<div id="hsm-cron-spinner" class="hsm-spinner" style="display: none;">
					<span class="spinner is-active"></span>
					<span><?php esc_html_e( 'Scheduling cron...', 'hypercart-server-monitor' ); ?></span>
				</div>

				<div id="hsm-cron-success" class="notice notice-success inline" style="display: none;">
					<p><strong><?php esc_html_e( 'Success!', 'hypercart-server-monitor' ); ?></strong> <span id="hsm-cron-success-message"></span></p>
				</div>

				<div id="hsm-cron-error" class="notice notice-error inline" style="display: none;">
					<p><strong><?php esc_html_e( 'Error:', 'hypercart-server-monitor' ); ?></strong> <span id="hsm-cron-error-message"></span></p>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Self Tests -->
	<div class="hsm-card">
		<h2><?php esc_html_e( 'Self Tests', 'hypercart-server-monitor' ); ?></h2>
		<table class="widefat">
			<tbody>
				<?php foreach ( $self_tests as $test ) : ?>
					<tr>
						<th><?php echo esc_html( $test['label'] ); ?></th>
						<td>
							<?php if ( 'ok' === $test['status'] ) : ?>
								<span class="hsm-status-ok"><?php echo esc_html( $test['detail'] ); ?></span>
							<?php else : ?>
								<span class="hsm-status-error"><?php echo esc_html( $test['detail'] ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Circuit Breaker Self Test -->
	<div class="hsm-card">
		<h2><?php esc_html_e( 'Circuit Breaker Self Test', 'hypercart-server-monitor' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Runs a safe simulation of trip/cooldown/probe and restores the original state.', 'hypercart-server-monitor' ); ?>
		</p>
		<button type="button" id="hsm-run-breaker-test" class="button button-secondary">
			<?php esc_html_e( 'Run Breaker Self Test', 'hypercart-server-monitor' ); ?>
		</button>
		<span id="hsm-breaker-test-spinner" class="spinner" style="float: none; vertical-align: middle; display: none;"></span>
		<div id="hsm-breaker-test-results" class="hsm-test-results" style="display: none;"></div>
		<div id="hsm-breaker-test-error" class="notice notice-error inline" style="display: none;">
			<p><strong><?php esc_html_e( 'Error:', 'hypercart-server-monitor' ); ?></strong> <span class="hsm-breaker-error-message"></span></p>
		</div>
	</div>

	<!-- Lock Status -->
	<div class="hsm-card">
		<h2><?php esc_html_e( 'Lock Status', 'hypercart-server-monitor' ); ?></h2>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Locked', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php if ( $lock_status['locked'] ) : ?>
							<span class="hsm-status-warning"><?php esc_html_e( 'Yes', 'hypercart-server-monitor' ); ?></span>
						<?php else : ?>
							<span class="hsm-status-ok"><?php esc_html_e( 'No', 'hypercart-server-monitor' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Acquired At', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php
						if ( ! empty( $lock_status['acquired_at'] ) ) {
							echo esc_html( \Hypercart_Time::format( 'Y-m-d H:i:s', $lock_status['acquired_at'] ) );
						} else {
							esc_html_e( 'N/A', 'hypercart-server-monitor' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Age', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php
						if ( ! empty( $lock_status['age_seconds'] ) ) {
							echo esc_html( number_format( $lock_status['age_seconds'] ) ) . ' seconds';
						} else {
							esc_html_e( 'N/A', 'hypercart-server-monitor' );
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- File Status -->
	<div class="hsm-card">
		<h2><?php esc_html_e( 'File Status', 'hypercart-server-monitor' ); ?></h2>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'File Path', 'hypercart-server-monitor' ); ?></th>
					<td><code><?php echo esc_html( $file_status['path'] ?? 'N/A' ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Exists', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php if ( $file_status['exists'] ?? false ) : ?>
							<span class="hsm-status-ok"><?php esc_html_e( 'Yes', 'hypercart-server-monitor' ); ?></span>
						<?php else : ?>
							<span class="hsm-status-warning"><?php esc_html_e( 'No', 'hypercart-server-monitor' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Writable', 'hypercart-server-monitor' ); ?></th>
					<td>
						<?php if ( $file_status['writable'] ?? false ) : ?>
							<span class="hsm-status-ok"><?php esc_html_e( 'Yes', 'hypercart-server-monitor' ); ?></span>
						<?php else : ?>
							<span class="hsm-status-error"><?php esc_html_e( 'No', 'hypercart-server-monitor' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $file_status['exists'] ?? false ) : ?>
					<tr>
						<th><?php esc_html_e( 'File Size', 'hypercart-server-monitor' ); ?></th>
						<td><?php echo esc_html( $file_status['size_kb'] ?? 0 ); ?> KB</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Sample Count', 'hypercart-server-monitor' ); ?></th>
						<td><?php echo esc_html( $file_status['sample_count'] ?? 0 ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Oldest Sample', 'hypercart-server-monitor' ); ?></th>
						<td>
							<?php
							if ( ! empty( $file_status['oldest_sample'] ) ) {
								echo esc_html( $file_status['oldest_sample'] );
							} else {
								esc_html_e( 'N/A', 'hypercart-server-monitor' );
							}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Newest Sample', 'hypercart-server-monitor' ); ?></th>
						<td>
							<?php
							if ( ! empty( $file_status['newest_sample'] ) ) {
								echo esc_html( $file_status['newest_sample'] );
							} else {
								esc_html_e( 'N/A', 'hypercart-server-monitor' );
							}
							?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#hsm-schedule-cron').on('click', function() {
		var $button = $(this);
		var $spinner = $('#hsm-cron-spinner');
		var $success = $('#hsm-cron-success');
		var $error = $('#hsm-cron-error');

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
				action: 'hsm_schedule_cron',
				nonce: hsmAdmin.nonce
			},
			success: function(response) {
				$spinner.hide();
				$button.prop('disabled', false);

				if (response.success) {
					var message = response.data.message + ' Next run: ' + response.data.next_run;
					$success.find('#hsm-cron-success-message').text(message);
					$success.show();

					// Reload page after 2 seconds to update cron status.
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					$error.find('#hsm-cron-error-message').text(response.data.message || 'Unknown error');
					$error.show();
				}
			},
			error: function(xhr, status, error) {
				$spinner.hide();
				$button.prop('disabled', false);
				$error.find('#hsm-cron-error-message').text('AJAX error: ' + error);
				$error.show();
			}
		});
	});
});
</script>
