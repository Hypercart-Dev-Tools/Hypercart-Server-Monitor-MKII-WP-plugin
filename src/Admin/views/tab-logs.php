<?php
/**
 * Logs Tab View
 *
 * @package Hypercart_Server_Monitor
 * @var array  $log_files     Available log files.
 * @var string $selected_file Selected log file.
 * @var string $log_content   Log file content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="hsm-logs">
	<div class="hsm-card">
		<h2><?php esc_html_e( 'Log Viewer', 'hypercart-server-monitor' ); ?></h2>

		<!-- Log File Selector -->
		<?php if ( ! empty( $log_files ) ) : ?>
			<form method="get" class="hsm-log-selector">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ?? '' ); ?>" />
				<input type="hidden" name="tab" value="logs" />
				<label for="log_file"><?php esc_html_e( 'Select Log File:', 'hypercart-server-monitor' ); ?></label>
				<select name="log_file" id="log_file" onchange="this.form.submit()">
					<?php foreach ( $log_files as $filename => $file_data ) : ?>
						<option value="<?php echo esc_attr( $filename ); ?>" <?php selected( $selected_file, $filename ); ?>>
							<?php
							printf(
								/* translators: 1: filename, 2: file size */
								esc_html__( '%1$s (%2$s)', 'hypercart-server-monitor' ),
								esc_html( $filename ),
								esc_html( size_format( $file_data['size'] ?? 0 ) )
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Load', 'hypercart-server-monitor' ); ?></button>
			</form>
		<?php endif; ?>

		<!-- Log Level Filter -->
		<div class="hsm-log-filters">
			<label><?php esc_html_e( 'Filter by Level:', 'hypercart-server-monitor' ); ?></label>
			<button type="button" class="button hsm-filter-btn active" data-level="all"><?php esc_html_e( 'All', 'hypercart-server-monitor' ); ?></button>
			<button type="button" class="button hsm-filter-btn" data-level="DEBUG"><?php esc_html_e( 'Debug', 'hypercart-server-monitor' ); ?></button>
			<button type="button" class="button hsm-filter-btn" data-level="INFO"><?php esc_html_e( 'Info', 'hypercart-server-monitor' ); ?></button>
			<button type="button" class="button hsm-filter-btn" data-level="WARNING"><?php esc_html_e( 'Warning', 'hypercart-server-monitor' ); ?></button>
			<button type="button" class="button hsm-filter-btn" data-level="ERROR"><?php esc_html_e( 'Error', 'hypercart-server-monitor' ); ?></button>
		</div>

		<!-- Log Content -->
		<?php if ( ! empty( $log_content ) ) : ?>
			<div class="hsm-log-content">
				<pre id="hsm-log-output"><?php
					// Parse and display log lines.
					$lines = explode( "\n", $log_content );
					$lines = array_reverse( $lines ); // Newest first.

					foreach ( $lines as $line ) {
						if ( empty( trim( $line ) ) ) {
							continue;
						}

						// Parse log line format: [YYYY-MM-DD HH:MM:SS UTC] plugin-slug LEVEL: Message {"context"}
						$level_class = 'hsm-log-line';
						if ( strpos( $line, 'ERROR:' ) !== false ) {
							$level_class .= ' hsm-log-error';
							$level = 'ERROR';
						} elseif ( strpos( $line, 'WARNING:' ) !== false ) {
							$level_class .= ' hsm-log-warning';
							$level = 'WARNING';
						} elseif ( strpos( $line, 'INFO:' ) !== false ) {
							$level_class .= ' hsm-log-info';
							$level = 'INFO';
						} elseif ( strpos( $line, 'DEBUG:' ) !== false ) {
							$level_class .= ' hsm-log-debug';
							$level = 'DEBUG';
						} else {
							$level = 'UNKNOWN';
						}

						// Only show lines from this plugin.
						if ( strpos( $line, 'hypercart-server-monitor' ) !== false ) {
							echo '<div class="' . esc_attr( $level_class ) . '" data-level="' . esc_attr( $level ) . '">';
							echo esc_html( $line );
							echo '</div>';
						}
					}
				?></pre>
			</div>
		<?php elseif ( empty( $log_files ) ) : ?>
			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'No log files found. Logs will appear here after the first monitoring run.', 'hypercart-server-monitor' ); ?></p>
			</div>
		<?php else : ?>
			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'Select a log file to view its contents.', 'hypercart-server-monitor' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<div class="hsm-card hsm-log-info">
		<h3><?php esc_html_e( 'About Logs', 'hypercart-server-monitor' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Logs are stored in wp-content/hypercart-logs/', 'hypercart-server-monitor' ); ?></li>
			<li><?php esc_html_e( 'Log files are named by date: hypercart-YYYY-MM-DD.log', 'hypercart-server-monitor' ); ?></li>
			<li><?php esc_html_e( 'Logs are displayed in reverse chronological order (newest first)', 'hypercart-server-monitor' ); ?></li>
			<li><?php esc_html_e( 'Only logs from this plugin are shown', 'hypercart-server-monitor' ); ?></li>
		</ul>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Log level filtering.
	$('.hsm-filter-btn').on('click', function() {
		var $btn = $(this);
		var level = $btn.data('level');

		// Update active state.
		$('.hsm-filter-btn').removeClass('active');
		$btn.addClass('active');

		// Filter log lines.
		if (level === 'all') {
			$('.hsm-log-line').show();
		} else {
			$('.hsm-log-line').hide();
			$('.hsm-log-line[data-level="' + level + '"]').show();
		}
	});
});
</script>

