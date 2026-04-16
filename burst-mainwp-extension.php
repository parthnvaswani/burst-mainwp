<?php
/**
 * Plugin Name: Burst MainWP Extension
 * Plugin URI:  https://burst-statistics.com
 * Description: View Burst Statistics data from child sites in your MainWP Dashboard.
 * Version:     1.0.0
 * Author:      Burst Statistics
 * Author URI:  https://burst-statistics.com
 * License:     GPL v3
 * Text Domain: burst-statistics
 * Requires PHP: 8.0
 *
 * @package BurstMainWP
 */

defined( 'ABSPATH' ) || die();

// ── Constants ────────────────────────────────────────────────────────────────

define( 'BURST_MAINWP_VERSION', '1.0.0' );
define( 'BURST_MAINWP_FILE', __FILE__ );
define( 'BURST_MAINWP_PATH', plugin_dir_path( __FILE__ ) );
define( 'BURST_MAINWP_URL', plugins_url( '', __FILE__ ) );
define( 'BURST_MAINWP_APP_URL', plugins_url( 'App', __FILE__ ) );
define( 'BURST_MAINWP_APP_PATH', plugin_dir_path( __FILE__ ) . 'App' );

// ── Autoloader ───────────────────────────────────────────────────────────────

spl_autoload_register( 'burst_mainwp_autoload' );

/**
 * Autoloader for BurstMainWP namespaced classes and legacy prefixed classes.
 *
 * Maps:
 * - BurstMainWP\\Foo_Bar   -> class/class-foo-bar.php
 * - Burst_MainWP_Foo_Bar   -> class/class-foo-bar.php
 */
function burst_mainwp_autoload( string $_class ): void {
	if ( strpos( $_class, 'BurstMainWP\\' ) === 0 ) {
		$relative = substr( $_class, strlen( 'BurstMainWP\\' ) );
		if ( strpos( $relative, 'Burst_MainWP_' ) === 0 ) {
			$relative = substr( $relative, strlen( 'Burst_MainWP_' ) );
		}
		$filename = 'class-' . strtolower( str_replace( [ '\\', '_' ], '-', $relative ) ) . '.php';
	} elseif ( strpos( $_class, 'Burst_MainWP_' ) === 0 ) {
		$filename = 'class-' . strtolower( str_replace( '_', '-', $_class ) ) . '.php';
	} else {
		return;
	}

	$path = BURST_MAINWP_PATH . 'class/' . $filename;

	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

// ── MainWP Extension Registration ────────────────────────────────────────────

add_filter( 'mainwp_getextensions', 'burst_mainwp_register_extension' );

/**
 * Register this plugin as a MainWP extension.
 *
 * This filter runs very early; keep it lightweight — no class instantiation here.
 *
 * @param array $extensions Registered MainWP extensions.
 * @return array Updated extensions list with Burst included.
 */
function burst_mainwp_register_extension( array $extensions ): array {
	$extensions[] = [
		'plugin' => BURST_MAINWP_FILE,
		'api'    => 'burst_mainwp_extension_api',
		'mainwp' => true,
		'slug'   => 'burst-mainwp-extension',
		'name'   => 'Burst Statistics',
	];
	return $extensions;
}

// ── Boot ──────────────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', 'burst_mainwp_init', 20 );
add_action( 'mainwp_activated', 'burst_mainwp_init' );

/**
 * Initialise extension once MainWP Dashboard is confirmed active and enabled.
 *
 * The static guard prevents double-boot when both hooks fire in the same request.
 */
function burst_mainwp_init(): void {
	static $initialized = false;
	if ( $initialized ) {
		burst_mainwp_debug_log( 'init skipped: already initialized for this request.' );
		return;
	}

	// MainWP Dashboard must be present.
	if ( ! class_exists( 'MainWP\Dashboard\MainWP_Connect' ) ) {
		burst_mainwp_debug_log( 'init skipped: MainWP_Connect class not found.' );
		return;
	}

	// Extension must be enabled / licensed in MainWP.
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	$check = apply_filters( 'mainwp_extension_enabled_check', BURST_MAINWP_FILE );
	if ( ! is_array( $check ) || ! isset( $check['key'] ) ) {
		burst_mainwp_debug_log( 'init skipped: extension not enabled by mainwp_extension_enabled_check.' );
		return;
	}

	$initialized = true;
	burst_mainwp_debug_log( 'init success: registering Individual integration.' );

	\BurstMainWP\Individual::instance();
}

/**
 * Write extension bootstrap debug messages when WP_DEBUG is enabled.
 *
 * @param string $message Human-readable message.
 * @return void
 */
function burst_mainwp_debug_log( string $message ): void {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Burst MainWP] ' . $message );
	}
}

// ── Activation ────────────────────────────────────────────────────────────────

register_activation_hook( BURST_MAINWP_FILE, 'burst_mainwp_activate' );

/**
 * Prevent activation when MainWP Dashboard is not active.
 */
function burst_mainwp_activate(): void {
	if ( ! is_plugin_active( 'mainwp/mainwp.php' ) ) {
		deactivate_plugins( plugin_basename( BURST_MAINWP_FILE ) );
		wp_die(
			esc_html__( 'Burst MainWP Extension requires MainWP Dashboard to be installed and activated.', 'burst-statistics' ),
			esc_html__( 'Plugin Activation Error', 'burst-statistics' ),
			[ 'back_link' => true ]
		);
	}
}
