<?php
/**
 * Settings Tab View
 *
 * @package Hypercart_Server_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current font settings (with defaults).
$font_settings = get_option( 'hypercart_server_monitor_font_settings', array() );
$defaults      = array(
	'timestamp_size'   => '14',
	'timestamp_weight' => '400',
	'timestamp_color'  => '#000000',
	'benchmark_size'   => '14',
	'benchmark_weight' => '400',
	'benchmark_color'  => '#000000',
	'health_size'      => '12',
	'health_weight'    => '600',
	'health_healthy'   => '#059669',
	'health_unhealthy' => '#dc2626',
);
$settings      = wp_parse_args( $font_settings, $defaults );
?>

<div class="hsm-settings-tab">
	<div class="hsm-card">
		<h2><?php esc_html_e( 'Table Font Customization', 'hypercart-server-monitor' ); ?></h2>
		<p><?php esc_html_e( 'Customize the appearance of table values in both admin dashboard and frontend shortcode.', 'hypercart-server-monitor' ); ?></p>

		<form id="hsm-font-settings-form" method="post">
			<?php wp_nonce_field( 'hsm_save_font_settings', 'hsm_font_settings_nonce' ); ?>

			<!-- Timestamp Settings -->
			<div class="hsm-settings-section">
				<h3><?php esc_html_e( 'Timestamp Column', 'hypercart-server-monitor' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="timestamp_size"><?php esc_html_e( 'Font Size (px)', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<input type="number" id="timestamp_size" name="font_settings[timestamp_size]" 
							       value="<?php echo esc_attr( $settings['timestamp_size'] ); ?>" 
							       min="8" max="32" step="1" class="small-text">
							<p class="description"><?php esc_html_e( 'Font size in pixels (8-32)', 'hypercart-server-monitor' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="timestamp_weight"><?php esc_html_e( 'Font Weight', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<select id="timestamp_weight" name="font_settings[timestamp_weight]">
								<option value="300" <?php selected( $settings['timestamp_weight'], '300' ); ?>><?php esc_html_e( 'Light (300)', 'hypercart-server-monitor' ); ?></option>
								<option value="400" <?php selected( $settings['timestamp_weight'], '400' ); ?>><?php esc_html_e( 'Normal (400)', 'hypercart-server-monitor' ); ?></option>
								<option value="500" <?php selected( $settings['timestamp_weight'], '500' ); ?>><?php esc_html_e( 'Medium (500)', 'hypercart-server-monitor' ); ?></option>
								<option value="600" <?php selected( $settings['timestamp_weight'], '600' ); ?>><?php esc_html_e( 'Semi-Bold (600)', 'hypercart-server-monitor' ); ?></option>
								<option value="700" <?php selected( $settings['timestamp_weight'], '700' ); ?>><?php esc_html_e( 'Bold (700)', 'hypercart-server-monitor' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="timestamp_color"><?php esc_html_e( 'Text Color', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<input type="text" id="timestamp_color" name="font_settings[timestamp_color]" 
							       value="<?php echo esc_attr( $settings['timestamp_color'] ); ?>" 
							       class="hsm-color-picker">
						</td>
					</tr>
				</table>
			</div>

			<!-- Benchmark Values Settings -->
			<div class="hsm-settings-section">
				<h3><?php esc_html_e( 'Benchmark Values (Run 1, Run 2, Run 3, Total)', 'hypercart-server-monitor' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="benchmark_size"><?php esc_html_e( 'Font Size (px)', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<input type="number" id="benchmark_size" name="font_settings[benchmark_size]" 
							       value="<?php echo esc_attr( $settings['benchmark_size'] ); ?>" 
							       min="8" max="32" step="1" class="small-text">
							<p class="description"><?php esc_html_e( 'Font size in pixels (8-32)', 'hypercart-server-monitor' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="benchmark_weight"><?php esc_html_e( 'Font Weight', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<select id="benchmark_weight" name="font_settings[benchmark_weight]">
								<option value="300" <?php selected( $settings['benchmark_weight'], '300' ); ?>><?php esc_html_e( 'Light (300)', 'hypercart-server-monitor' ); ?></option>
								<option value="400" <?php selected( $settings['benchmark_weight'], '400' ); ?>><?php esc_html_e( 'Normal (400)', 'hypercart-server-monitor' ); ?></option>
								<option value="500" <?php selected( $settings['benchmark_weight'], '500' ); ?>><?php esc_html_e( 'Medium (500)', 'hypercart-server-monitor' ); ?></option>
								<option value="600" <?php selected( $settings['benchmark_weight'], '600' ); ?>><?php esc_html_e( 'Semi-Bold (600)', 'hypercart-server-monitor' ); ?></option>
								<option value="700" <?php selected( $settings['benchmark_weight'], '700' ); ?>><?php esc_html_e( 'Bold (700)', 'hypercart-server-monitor' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="benchmark_color"><?php esc_html_e( 'Text Color', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<input type="text" id="benchmark_color" name="font_settings[benchmark_color]" 
							       value="<?php echo esc_attr( $settings['benchmark_color'] ); ?>" 
							       class="hsm-color-picker">
						</td>
					</tr>
				</table>
			</div>

			<!-- Health Status Settings -->
			<div class="hsm-settings-section">
				<h3><?php esc_html_e( 'Cron Health Status', 'hypercart-server-monitor' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="health_size"><?php esc_html_e( 'Font Size (px)', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<input type="number" id="health_size" name="font_settings[health_size]"
							       value="<?php echo esc_attr( $settings['health_size'] ); ?>"
							       min="8" max="32" step="1" class="small-text">
							<p class="description"><?php esc_html_e( 'Font size in pixels (8-32)', 'hypercart-server-monitor' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="health_weight"><?php esc_html_e( 'Font Weight', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<select id="health_weight" name="font_settings[health_weight]">
								<option value="300" <?php selected( $settings['health_weight'], '300' ); ?>><?php esc_html_e( 'Light (300)', 'hypercart-server-monitor' ); ?></option>
								<option value="400" <?php selected( $settings['health_weight'], '400' ); ?>><?php esc_html_e( 'Normal (400)', 'hypercart-server-monitor' ); ?></option>
								<option value="500" <?php selected( $settings['health_weight'], '500' ); ?>><?php esc_html_e( 'Medium (500)', 'hypercart-server-monitor' ); ?></option>
								<option value="600" <?php selected( $settings['health_weight'], '600' ); ?>><?php esc_html_e( 'Semi-Bold (600)', 'hypercart-server-monitor' ); ?></option>
								<option value="700" <?php selected( $settings['health_weight'], '700' ); ?>><?php esc_html_e( 'Bold (700)', 'hypercart-server-monitor' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="health_healthy"><?php esc_html_e( 'Healthy Color', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<input type="text" id="health_healthy" name="font_settings[health_healthy]"
							       value="<?php echo esc_attr( $settings['health_healthy'] ); ?>"
							       class="hsm-color-picker">
							<p class="description"><?php esc_html_e( 'Color for "HEALTHY" status', 'hypercart-server-monitor' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="health_unhealthy"><?php esc_html_e( 'Unhealthy Color', 'hypercart-server-monitor' ); ?></label>
						</th>
						<td>
							<input type="text" id="health_unhealthy" name="font_settings[health_unhealthy]"
							       value="<?php echo esc_attr( $settings['health_unhealthy'] ); ?>"
							       class="hsm-color-picker">
							<p class="description"><?php esc_html_e( 'Color for "UNHEALTHY" status', 'hypercart-server-monitor' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Save Button -->
			<p class="submit">
				<button type="submit" class="button button-primary button-large">
					<?php esc_html_e( 'Save Font Settings', 'hypercart-server-monitor' ); ?>
				</button>
				<button type="button" id="hsm-reset-font-settings" class="button button-secondary">
					<?php esc_html_e( 'Reset to Defaults', 'hypercart-server-monitor' ); ?>
				</button>
			</p>

			<!-- Live Preview -->
			<div class="hsm-settings-section">
				<h3><?php esc_html_e( 'Live Preview', 'hypercart-server-monitor' ); ?></h3>
				<div class="hsm-table-wrapper">
					<table class="hsm-table" id="hsm-font-preview-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Timestamp', 'hypercart-server-monitor' ); ?></th>
								<th><?php esc_html_e( 'Run 1 (ms)', 'hypercart-server-monitor' ); ?></th>
								<th><?php esc_html_e( 'Run 2 (ms)', 'hypercart-server-monitor' ); ?></th>
								<th><?php esc_html_e( 'Run 3 (ms)', 'hypercart-server-monitor' ); ?></th>
								<th><?php esc_html_e( 'Total (ms)', 'hypercart-server-monitor' ); ?></th>
								<th><?php esc_html_e( 'Cron Health', 'hypercart-server-monitor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="hsm-table-timestamp">2026-01-31 12:00:00</td>
								<td class="hsm-table-value">42.38</td>
								<td class="hsm-table-value">43.83</td>
								<td class="hsm-table-value">44.07</td>
								<td class="hsm-table-value">130.28</td>
								<td><span class="hsm-cron-health healthy">HEALTHY</span></td>
							</tr>
							<tr>
								<td class="hsm-table-timestamp">2026-01-31 11:45:00</td>
								<td class="hsm-table-value">45.12</td>
								<td class="hsm-table-value">46.23</td>
								<td class="hsm-table-value">47.34</td>
								<td class="hsm-table-value">138.69</td>
								<td><span class="hsm-cron-health unhealthy">UNHEALTHY</span></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</form>
	</div>

	<!-- Success/Error Messages -->
	<div id="hsm-settings-message" class="notice" style="display: none;">
		<p></p>
	</div>
</div>

