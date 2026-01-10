<?php
/**
 * Plugin Name: Hypercart Server Monitor MKII
 * Plugin URI: https://github.com/yourusername/wp-server-performance-monitor
 * Description: Monitors server health (CPU, Memory, Disk) every 15 minutes with email alerts and admin dashboard. Requires Hypercart Helper.
 * Version: 0.1.0
 * Author: Your Name
 * Author URI: https://hypercart.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hypercart-server-monitor
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Hypercart_Server_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'HYPERCART_SERVER_MONITOR_VERSION', '0.1.0' );
define( 'HYPERCART_SERVER_MONITOR_PLUGIN_FILE', __FILE__ );
define( 'HYPERCART_SERVER_MONITOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HYPERCART_SERVER_MONITOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HYPERCART_SERVER_MONITOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check Hypercart Helper dependency.
 *
 * @return bool True if dependency met, false otherwise.
 */
function hypercart_server_monitor_check_dependencies() {
	// Check if required classes exist.
	if ( ! class_exists( 'Hypercart_Time' ) || ! class_exists( 'Hypercart_Logger' ) ) {
		add_action( 'admin_notices', 'hypercart_server_monitor_missing_dependency_notice' );
		return false;
	}

	// Check minimum version.
	if ( defined( 'HYPERCART_HELPER_VERSION' ) &&
	     version_compare( HYPERCART_HELPER_VERSION, '1.0.0', '<' ) ) {
		add_action( 'admin_notices', 'hypercart_server_monitor_version_mismatch_notice' );
		return false;
	}

	return true;
}

/**
 * Display admin notice for missing Hypercart Helper.
 */
function hypercart_server_monitor_missing_dependency_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong>Hypercart Server Monitor MKII</strong> requires
			<strong>Hypercart Helper v1.0.0+</strong> to be installed and active.
			<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
				Manage Plugins
			</a>
		</p>
	</div>
	<?php
}

/**
 * Display admin notice for version mismatch.
 */
function hypercart_server_monitor_version_mismatch_notice() {
	$current_version = defined( 'HYPERCART_HELPER_VERSION' ) ? HYPERCART_HELPER_VERSION : 'unknown';
	?>
	<div class="notice notice-error">
		<p>
			<strong>Hypercart Server Monitor MKII</strong> requires
			<strong>Hypercart Helper v1.0.0+</strong>
			(found v<?php echo esc_html( $current_version ); ?>).
		</p>
	</div>
	<?php
}

// Early dependency check - don't load plugin if dependency missing.
if ( ! hypercart_server_monitor_check_dependencies() ) {
	return;
}

// Require Composer autoloader if it exists.
if ( file_exists( HYPERCART_SERVER_MONITOR_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once HYPERCART_SERVER_MONITOR_PLUGIN_DIR . 'vendor/autoload.php';
}

// Require main plugin class.
require_once HYPERCART_SERVER_MONITOR_PLUGIN_DIR . 'src/Plugin.php';

/**
 * Initialize the plugin.
 */
function hypercart_server_monitor_init() {
	// Log plugin initialization.
	Hypercart_Logger::info(
		'hypercart-server-monitor',
		'Plugin initializing',
		array(
			'version' => HYPERCART_SERVER_MONITOR_VERSION,
			'php_version' => PHP_VERSION,
			'wp_version' => get_bloginfo( 'version' ),
		)
	);

	// Initialize plugin.
	Hypercart_Server_Monitor\Plugin::get_instance();
}
add_action( 'plugins_loaded', 'hypercart_server_monitor_init' );

/**
 * Activation hook.
 */
function hypercart_server_monitor_activate() {
	// Log activation.
	Hypercart_Logger::info(
		'hypercart-server-monitor',
		'Plugin activated',
		array( 'version' => HYPERCART_SERVER_MONITOR_VERSION )
	);

	// Run activation tasks.
	if ( class_exists( 'Hypercart_Server_Monitor\Plugin' ) ) {
		Hypercart_Server_Monitor\Plugin::activate();
	}
}
register_activation_hook( __FILE__, 'hypercart_server_monitor_activate' );

/**
 * Deactivation hook.
 */
function hypercart_server_monitor_deactivate() {
	// Log deactivation.
	Hypercart_Logger::info(
		'hypercart-server-monitor',
		'Plugin deactivated',
		array( 'version' => HYPERCART_SERVER_MONITOR_VERSION )
	);

	// Run deactivation tasks.
	if ( class_exists( 'Hypercart_Server_Monitor\Plugin' ) ) {
		Hypercart_Server_Monitor\Plugin::deactivate();
	}
}
register_deactivation_hook( __FILE__, 'hypercart_server_monitor_deactivate' );

