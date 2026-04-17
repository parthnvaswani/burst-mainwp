<?php
/**
 * Burst Statistics – MainWP Individual Site Integration
 *
 * Registers the per-site sub-page and site-overview widget, then renders the
 * React statistics app with child-site REST credentials injected via
 * `wp_localize_script`.
 *
 * @package Burst_Statistics_MainWP
 */

namespace BurstMainWP;

defined( 'ABSPATH' ) || exit;

class Individual {

	private static ?self $instance = null;

	/**
	 * Singleton instance accessor.
	 *
	 * @return self Singleton instance of the Individual integration class.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** The constructor is private to enforce singleton usage.  This class is not
	 * designed to be used with hooks or instantiated multiple times; instead, its
	 * methods are called explicitly during page rendering when needed.
	 */
	private function __construct() {
		add_filter( 'mainwp_getsubpages_sites', [ $this, 'add_subpage' ] );
	}

	// ── MainWP Hooks ──────────────────────────────────────────────────────────

	/**
	 * Add a "Burst Statistics" sub-page to individual site views.
	 *
	 * @param array $subpages Existing sub-pages registered by MainWP / other extensions.
	 * @return array Modified sub-pages with the Burst Statistics page added.
	 */
	public function add_subpage( array $subpages ): array {
		$subpages[] = [
			'title'       => esc_html__( 'Burst Statistics', 'burst-statistics' ),
			'slug'        => 'BurstStatistics',
			'sitetab'     => true,
			'menu_hidden' => true,
			'callback'    => [ $this, 'render_individual_site' ],
		];
		return $subpages;
	}

	// ── Individual Site Page ──────────────────────────────────────────────────

	/**
	 * Render the full Burst Statistics page for a single child site.
	 *
	 * MainWP calls this as the registered `callback` for the BurstStatistics sub-page.
	 */
	public function render_individual_site(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'mainwp_pageheader_sites', 'BurstStatistics' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$site_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( 0 === $site_id ) {
			echo '<div class="ui red message">' . esc_html__( 'Invalid site ID.', 'burst-statistics' ) . '</div>';
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'mainwp_pagefooter_sites', 'BurstStatistics' );
			return;
		}

		$website = API::instance()->get_site_data( $site_id );

		if ( ! $website ) {
			echo '<div class="ui red message">' . esc_html__( 'Site not found.', 'burst-statistics' ) . '</div>';
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'mainwp_pagefooter_sites', 'BurstStatistics' );
			return;
		}

		$this->render_content( $website );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'mainwp_pagefooter_sites', 'BurstStatistics' );
	}

	/**
	 * Render the React app shell and enqueue all required assets.
	 *
	 * Child-site REST credentials are fetched here (during a full MainWP page
	 * load where signing infrastructure is available) and forwarded to the
	 * React app via `wp_localize_script`.
	 *
	 * @param object $website MainWP website row object.
	 */
	public function render_content( object $website ): void {
		$child_data = API::instance()->get_child_auth( (int) $website->id );

		if ( ! $child_data ) {
			echo '<div class="ui red message">'
				. esc_html__(
					'Could not connect to child site. Please ensure Burst Statistics is installed and active on the child site.',
					'burst-statistics'
				)
				. '</div>';
			return;
		}

		?>
		<div id="mainwp-burst-statistics">
			<div id="burst-statistics" class="burst" data-site-id="<?php echo esc_attr( $website->id ); ?>"
				data-site-url="<?php echo esc_url( $website->url ); ?>">
			</div>
		</div>
		<?php

		$js_data = self::get_chunk_translations( 'App/build' );
		if ( empty( $js_data['js_file'] ) ) {
			return;
		}

		$version = $js_data['version'];

		wp_enqueue_style(
			'burst-tailwind',
			BURST_MAINWP_APP_URL . '/src/tailwind.generated.css',
			[],
			$version
		);

		$dependencies   = $js_data['dependencies'];
		$dependencies[] = 'wp-core-data';

		wp_enqueue_script(
			'burst-settings',
			BURST_MAINWP_APP_URL . '/build/' . $js_data['js_file'],
			$dependencies,
			$version,
			[
				'strategy'  => 'async',
				'in_footer' => false,
			]
		);

		wp_localize_script(
			'burst-settings',
			'burst_settings',
			$this->build_localized_settings( $js_data, $child_data, $website )
		);
	}

	// ── Localization ──────────────────────────────────────────────────────────

	/**
	 * Build the `burst_settings` object that is passed to the React app.
	 *
	 * Child-site REST credentials (`root`, `nonce`, `child_token`) override the
	 * defaults so that every `apiFetch` call targets the child instead of the
	 * MainWP dashboard.
	 *
	 * @param array  $js_data    Asset manifest data from {@see get_chunk_translations()}.
	 * @param array  $child_data Auth data returned by {@see API::get_child_auth()}.
	 * @param object $website    MainWP website row object.
	 * @return array<string,mixed>
	 */
	private function build_localized_settings( array $js_data, array $child_data, object $website ): array {
		$child_root        = trailingslashit( $child_data['root_url'] );
		$localization_data = is_array( $child_data['localization_data'] ?? null ) ? $child_data['localization_data'] : [];

		$settings = [
			// ── Core plugin information ──────────────────────────────────────
			'plugin_url'        => trailingslashit( BURST_MAINWP_URL ),

			// ── Child-site REST credentials ──────────────────────────────────
			// These override the dashboard URLs so apiFetch calls the child.
			'root'              => $child_root,
			'child_token'       => $child_data['token'],
			'site_url'          => trailingslashit( $website->url ),

			// ── MainWP context flag ──────────────────────────────────────────
			// React uses this to configure the apiFetch middleware.
			'is_mainwp'         => true,

			// ── Localization ─────────────────────────────────────────────────
			'json_translations' => $js_data['json_translations'],
		];

		// Put settings into localization_data so they are available in the same object in React.
		foreach ( $settings as $key => $value ) {
			$localization_data[ $key ] = $value;
		}

		// Remove "Customization" sub-menu from the "Reporting" menu if it exists, as it's not working in the MainWP context.
		if ( isset( $localization_data['menu'] ) && is_array( $localization_data['menu'] ) ) {
			foreach ( $localization_data['menu'] as &$menu_item ) {
				if (
					isset( $menu_item['id'], $menu_item['menu_items'] ) &&
					'reporting' === $menu_item['id'] &&
					is_array( $menu_item['menu_items'] )
				) {
					$menu_item['menu_items'] = array_values(
						array_filter(
							$menu_item['menu_items'],
							fn( $sub_menu_item ) => ! ( isset( $sub_menu_item['id'] ) && 'customization' === $sub_menu_item['id'] )
						)
					);
				}
			}
			unset( $menu_item );
		}

		return apply_filters(
			'burst_localize_script',
			$localization_data
		);
	}

	/**
	 * Collect and return JSON translation blobs for all React code-split chunks.
	 *
	 * Works by enumerating locale JSON files from the languages directory that
	 * match the current locale, temporarily registering a dummy script handle
	 * to load the translation data, then immediately de-registering it.
	 *
	 * @param string $dir Path relative to BURST_MAINWP_PATH (e.g. 'App/build').
	 * @return array{json_translations:array,js_file:string,dependencies:array,version:string}
	 */
	public static function get_chunk_translations( string $dir ): array {
		$default = [
			'json_translations' => [],
			'js_file'           => '',
			'dependencies'      => [],
			'version'           => '',
		];

		$text_domain   = 'burst-statistics';
		$languages_dir = WP_CONTENT_DIR . '/languages/plugins';

		$locale            = determine_locale();
		$json_translations = [];
		$language_files    = [];

		if ( is_dir( $languages_dir ) ) {
			$pattern        = "$languages_dir/{$text_domain}-{$locale}-*.json";
			$language_files = glob( $pattern ) ?: [];
		}

		foreach ( $language_files as $language_file ) {
			$src    = basename( $language_file );
			$handle = str_replace( [ $text_domain . '-', $locale . '-', '.json' ], '', $src );

			wp_register_script( $handle, plugins_url( $src, __FILE__ ), [], true, true );
			$locale_data = load_script_textdomain( $handle, $text_domain, $languages_dir );
			wp_deregister_script( $handle );

			if ( ! empty( $locale_data ) ) {
				$json_translations[] = $locale_data;
			}
		}

		$build_path  = BURST_MAINWP_PATH . $dir . '/';
		$js_files    = glob( $build_path . 'index*.js' ) ?: [];
		$asset_files = glob( $build_path . 'index*.asset.php' ) ?: [];

		if ( empty( $js_files ) || empty( $asset_files ) ) {
			return $default;
		}

		$js_filename    = basename( $js_files[0] );
		$asset_filename = basename( $asset_files[0] );
		$asset_path     = $build_path . $asset_filename;

		if ( ! file_exists( $asset_path ) ) {
			return $default;
		}

		$asset_file = require $asset_path;

		return [
			'json_translations' => $json_translations,
			'js_file'           => $js_filename,
			'dependencies'      => $asset_file['dependencies'] ?? [],
			'version'           => $asset_file['version'] ?? BURST_MAINWP_VERSION,
		];
	}
}
