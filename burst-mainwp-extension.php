<?php
/**
 * Plugin Name: Burst MainWP Extension
 * Plugin URI:  https://burst-statistics.com
 * Description: View Burst Statistics data from child sites in your MainWP Dashboard.
 * Version:     1.0.0
 * Author:      Burst Statistics
 * Author URI:  https://burst-statistics.com
 * License:     GPL v3
 * Text Domain: burst-mainwp-extension
 * Requires PHP: 8.0
 *
 * @package Burst_Statistics_MainWP
 */

defined( 'ABSPATH' ) || die();

// ── Constants ────────────────────────────────────────────────────────────────

define( 'BURST_MAINWP_VERSION', '1.0.0' );
define( 'BURST_MAINWP_FILE', __FILE__ );
define( 'BURST_PATH', plugin_dir_path( __FILE__ ) );
define( 'BURST_URL', plugins_url( '', __FILE__ ) );
define( 'BURST_APP_URL', plugins_url( 'App', __FILE__ ) );
define( 'BURST_APP_PATH', plugin_dir_path( __FILE__ ) . 'App' );

/*
 * BURST_PRO: define only when this is the Pro build.
 * Un-comment the line below when shipping the Pro edition.
 */
define( 'BURST_PRO', true );

// ── Autoloader ───────────────────────────────────────────────────────────────

spl_autoload_register( 'burst_mainwp_autoload' );

/**
 * PSR-4-style autoloader for Burst_MainWP_* classes.
 *
 * Maps  Burst_MainWP_Foo_Bar  →  class/class-burst-mainwp-foo-bar.php
 */
function burst_mainwp_autoload( string $_class ): void {
	if ( strpos( $_class, 'Burst_MainWP_' ) !== 0 ) {
		return;
	}

	$filename = 'class-' . strtolower( str_replace( '_', '-', $_class ) ) . '.php';
	$path     = BURST_PATH . 'class/' . $filename;

	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

// ── Helpers ───────────────────────────────────────────────────────────────────

require_once BURST_PATH . 'functions.php';

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
		return;
	}

	// MainWP Dashboard must be present.
	if ( ! class_exists( 'MainWP\Dashboard\MainWP_Connect' ) ) {
		return;
	}

	// Extension must be enabled / licensed in MainWP.
	$check = apply_filters( 'burst_mainwp_extension_enabled_check', BURST_MAINWP_FILE );
	if ( ! is_array( $check ) || ! isset( $check['key'] ) ) {
		return;
	}

	$initialized = true;

	Burst_MainWP_Individual::instance();
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
