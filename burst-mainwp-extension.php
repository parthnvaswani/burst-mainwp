<?php
/**
 * Plugin Name: Burst MainWP Extension
 * Plugin URI:  https://burst-statistics.com
 * Description: View Burst Statistics data from child sites in your MainWP Dashboard.
 * Version:     1.0.0
 * Author:      Burst Statistics
 * Author URI:  https://burst-statistics.com
 * License:     GPL v3
 * Text Domain: burst-mainwp
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
		'plugin'   => BURST_MAINWP_FILE,
		'api'      => 'burst-mainwp-extension',
		'callback' => 'burst_mainwp_extension',
		'mainwp'   => true,
		'slug'     => 'burst-mainwp-extension',
		'name'     => 'Burst Statistics',
	];
	return $extensions;
}

/**
 * Render the MainWP Extensions landing page for Burst.
 *
 * This page intentionally keeps a short "how to use" message and points users
 * to the per-site Burst dashboard, which is where all functionality lives.
 */
function burst_mainwp_extension(): void {
	$manage_sites_url = admin_url( 'admin.php?page=managesites' );

	// Render inside MainWP's standard extension page chrome.
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	do_action( 'mainwp_pageheader_extensions', BURST_MAINWP_FILE );

	echo '<div class="ui segment">';
	echo '<h2 style="color: #fff;">' . esc_html__( 'Burst Statistics', 'burst-mainwp' ) . '</h2>';
	echo '<p>' . esc_html__( 'This MainWP extension does not have a separate global settings screen. Use Burst Statistics from each child site page in MainWP.', 'burst-mainwp' ) . '</p>';
	echo '<ol>';
	echo '<li>' . esc_html__( 'Open MainWP -> Sites.', 'burst-mainwp' ) . '</li>';
	echo '<li>' . esc_html__( 'Open any child site.', 'burst-mainwp' ) . '</li>';
	echo '<li>' . esc_html__( 'Click Burst Statistics in the site sidebar to open the full dashboard.', 'burst-mainwp' ) . '</li>';
	echo '</ol>';
	echo '<p><a class="ui button green" href="' . esc_url( $manage_sites_url ) . '">' . esc_html__( 'Go to Sites', 'burst-mainwp' ) . '</a></p>';
	echo '</div>';

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	do_action( 'mainwp_pagefooter_extensions', BURST_MAINWP_FILE );
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
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	$check = apply_filters( 'mainwp_extension_enabled_check', BURST_MAINWP_FILE );
	if ( ! is_array( $check ) || ! isset( $check['key'] ) ) {
		return;
	}

	$initialized = true;

	\BurstMainWP\Individual::instance();
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
			esc_html__( 'Burst MainWP Extension requires MainWP Dashboard to be installed and activated.', 'burst-mainwp' ),
			esc_html__( 'Plugin Activation Error', 'burst-mainwp' ),
			[ 'back_link' => true ]
		);
	}
}
