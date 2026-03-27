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

defined( 'ABSPATH' ) || exit;

class Burst_MainWP_Individual {

	private static ?self $instance = null;

	/**
	 * Child-site data returned by {@see Burst_MainWP_API::get_child_auth()}.
	 * Populated during {@see render_content()} and consumed by capability helpers.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $child_data = null;

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
		add_filter( 'mainwp_getmetaboxes', [ $this, 'register_widget' ] );
	}

	/**
	 * Return the child-site options array, or an empty array when not yet set.
	 *
	 * Used by {@see burst_get_option()} so that function never touches raw
	 * internal state directly.
	 *
	 * @return array<string,mixed>
	 */
	public function get_child_options(): array {
		return $this->child_data['options'] ?? [];
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

	/**
	 * Register a quick-stats widget on the MainWP site-overview dashboard.
	 *
	 * @param array $metaboxes Registered meta-boxes.
	 * @return array Modified meta-boxes with the Burst Statistics widget added.
	 */
	public function register_widget( array $metaboxes ): array {
		$metaboxes[] = [
			'id'       => 'burst-statistics-widget',
			'title'    => esc_html__( 'Burst Statistics', 'burst-statistics' ),
			'callback' => [ $this, 'render_widget' ],
			'page'     => [ 'ManageSitesDashboard' ],
			'context'  => 'normal',
			'priority' => 'core',
		];
		return $metaboxes;
	}

	// ── Widget ────────────────────────────────────────────────────────────────

	/**
	 * Render the compact quick-stats widget shown on the site overview page.
	 *
	 * Intentionally shows placeholder values — the full React app is available
	 * via the "View Full Statistics" link, which is the primary interaction.
	 */
	public function render_widget(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$website_id = isset( $_GET['dashboard'] ) ? absint( $_GET['dashboard'] ) : 0;

		if ( ! $website_id ) {
			echo '<p>' . esc_html__( 'No site selected.', 'burst-statistics' ) . '</p>';
			return;
		}
		?>
		<div class="burst-widget-preview">
			<h3><?php esc_html_e( 'Quick Stats (Last 7 Days)', 'burst-statistics' ); ?></h3>
			<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:15px;margin:20px 0;">
				<div style="padding:15px;background:#f8f9fa;border-radius:4px;">
					<div style="font-size:24px;font-weight:600;">--</div>
					<div style="font-size:12px;color:#666;"><?php esc_html_e( 'Page Views', 'burst-statistics' ); ?></div>
				</div>
				<div style="padding:15px;background:#f8f9fa;border-radius:4px;">
					<div style="font-size:24px;font-weight:600;">--</div>
					<div style="font-size:12px;color:#666;"><?php esc_html_e( 'Visitors', 'burst-statistics' ); ?></div>
				</div>
			</div>
			<div style="text-align:center;padding-top:15px;border-top:1px solid #e0e0e0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ManageSitesBurstStatistics&id=' . $website_id ) ); ?>"
					class="button button-primary">
					<?php esc_html_e( 'View Full Statistics', 'burst-statistics' ); ?>
				</a>
			</div>
		</div>
		<?php
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

		if ( ! $site_id ) {
			echo '<div class="ui red message">' . esc_html__( 'Invalid site ID.', 'burst-statistics' ) . '</div>';
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'mainwp_pagefooter_sites', 'BurstStatistics' );
			return;
		}

		$website = Burst_MainWP_API::instance()->get_site_data( $site_id );

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
		$child_data = Burst_MainWP_API::instance()->get_child_auth( (int) $website->id );

		if ( ! $child_data ) {
			echo '<div class="ui red message">'
				. esc_html__(
					'Could not connect to child site. Please ensure Burst Statistics is installed and active on the child site.',
					'burst-statistics'
				)
				. '</div>';
			return;
		}

		// Cache for use by capability helpers and burst_get_option().
		$this->child_data = $child_data;
		?>
		<div id="mainwp-burst-statistics">
			<div id="burst-statistics" data-site-id="<?php echo esc_attr( $website->id ); ?>"
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
			BURST_APP_URL . '/src/tailwind.generated.css',
			[],
			$version
		);

		$dependencies   = $js_data['dependencies'];
		$dependencies[] = 'wp-core-data';

		wp_enqueue_script(
			'burst-settings',
			BURST_APP_URL . '/build/' . $js_data['js_file'],
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
	 * @param array  $child_data Auth data returned by {@see Burst_MainWP_API::get_child_auth()}.
	 * @param object $website    MainWP website row object.
	 * @return array<string,mixed>
	 */
	private function build_localized_settings( array $js_data, array $child_data, object $website ): array {
		$child_root = trailingslashit( $child_data['root_url'] );
		$extra      = is_array( $child_data['extra'] ?? null ) ? $child_data['extra'] : [];

		$settings = [
			// ── Core plugin information ──────────────────────────────────────
			'is_pro'                      => defined( 'BURST_PRO' ),
			'plugin_url'                  => trailingslashit( BURST_URL ),
			'installed_by'                => burst_get_option( 'teamupdraft_installation_source_burst-statistics', '' ),

			// ── Child-site REST credentials ──────────────────────────────────
			// These override the dashboard URLs so apiFetch calls the child.
			'root'                        => $child_root,
			'nonce'                       => $child_data['nonce'],
			'burst_nonce'                 => $child_data['nonce'],
			'child_token'                 => $child_data['token'],
			'site_url'                    => trailingslashit( $website->url ),

			// ── User permissions ─────────────────────────────────────────────
			'user_roles'                  => $this->get_user_roles(),
			'view_sales_burst_statistics' => $this->user_can_view_sales(),
			'manage_burst_statistics'     => $this->user_can_manage(),
			'can_install_plugins'         => $this->child_can( 'install_plugins' ),
			'share_link_permissions'      => [
				'can_change_date'          => true,
				'can_filter'               => true,
				'is_shareable_link_viewer' => false,
			],

			// ── MainWP context flag ──────────────────────────────────────────
			// React uses this to configure the apiFetch middleware.
			'is_mainwp'                   => true,

			// ── Localization ─────────────────────────────────────────────────
			'json_translations'           => $js_data['json_translations'],
			'date_format'                 => get_option( 'date_format' ),
			'gmt_offset'                  => get_option( 'gmt_offset' ),
			'time_format'                 => get_option( 'time_format' ),

			// ── Config ───────────────────────────────────────────────────────
			'date_ranges'                 => $this->get_date_ranges(),
			'tour_shown'                  => true,
			'current_ip'                  => self::get_ip_address(),
		];

		// Merge extra data forwarded from the child (e.g. feature flags, options).
		// Child keys must NOT silently overwrite security-critical settings.
		$protected = [ 'nonce', 'burst_nonce', 'child_token', 'root', 'is_mainwp' ];
		foreach ( $protected as $key ) {
			unset( $extra[ $key ] );
		}

		return apply_filters(
			'burst_localize_script',
			array_merge( $settings, $extra )
		);
	}

	// ── Capability helpers ────────────────────────────────────────────────────

	/**
	 * Check whether the current child-site user holds a given capability.
	 *
	 * Capabilities are forwarded by the child during auth and stored in
	 * `child_data['capabilities']` as `[ 'cap_name' => 1|0 ]`.
	 *
	 * @param string $capability Capability slug.
	 * @return bool True if the capability is present and truthy, false otherwise.
	 */
	private function child_can( string $capability ): bool {
		$caps = $this->child_data['capabilities'] ?? [];
		return isset( $caps[ $capability ] ) && (int) $caps[ $capability ] === 1;
	}

	/**
	 * Check if the current user can manage Burst Statistics on the child site.
	 *
	 * @return bool True if the user can manage, false otherwise.
	 */
	public function user_can_manage(): bool {
		return $this->child_can( 'manage_burst_statistics' );
	}

	/**
	 * Check if the current user can view Burst Statistics on the child site.
	 *
	 * @return bool True if the user can view, false otherwise.
	 */
	public function user_can_view(): bool {
		return $this->child_can( 'view_burst_statistics' );
	}

	/**
	 * Check if the current user can view sales statistics on the child site.
	 *
	 * @return bool True if the user can view sales stats, false otherwise.
	 */
	public function user_can_view_sales(): bool {
		return $this->child_can( 'view_sales_burst_statistics' );
	}

	// ── Static helpers ────────────────────────────────────────────────────────

	/**
	 * Return the list of allowed date ranges for the React date-picker.
	 *
	 * @return string[]
	 */
	public function get_date_ranges(): array {
		return apply_filters(
			'burst_date_ranges',
			[
				'today',
				'yesterday',
				'last-7-days',
				'last-30-days',
				'last-90-days',
				'last-month',
				'last-year',
				'week-to-date',
				'month-to-date',
				'year-to-date',
			]
		);
	}

	/**
	 * Collect and return JSON translation blobs for all React code-split chunks.
	 *
	 * Works by enumerating locale JSON files from the languages directory that
	 * match the current locale, temporarily registering a dummy script handle
	 * to load the translation data, then immediately de-registering it.
	 *
	 * @param string $dir Path relative to BURST_PATH (e.g. 'App/build').
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
		$languages_dir = defined( 'BURST_PRO' )
			? BURST_PATH . 'languages'
			: WP_CONTENT_DIR . '/languages/plugins';

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

		$build_path  = BURST_PATH . $dir . '/';
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

	/**
	 * Determine the visitor's real IP address.
	 *
	 * Preference order: Cloudflare → True-Client-IP → X-Forwarded-For →
	 * X-Real-IP → X-Cluster-Client-IP → Client-IP → REMOTE_ADDR.
	 * Loopback and private-range addresses are de-prioritised.
	 *
	 * @return string Valid IP or empty string.
	 */
	public static function get_ip_address(): string {
		$candidates = [];

		$headers = [
			'HTTP_CF_CONNECTING_IP',
			'HTTP_TRUE_CLIENT_IP',
		];
		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$candidates[] = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
			}
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			foreach ( explode( ',', $forwarded ) as $part ) {
				$candidates[] = trim( $part );
			}
		}

		foreach ( [ 'HTTP_X_REAL_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ] as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$candidates[] = sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) );
			}
		}

		$valid = [];
		foreach ( $candidates as $ip ) {
			$ip = trim( $ip );
			if ( $ip === '' || $ip === '127.0.0.1' ) {
				continue;
			}
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
				$valid[] = $ip;
			}
		}

		if ( empty( $valid ) ) {
			return (string) apply_filters( 'burst_visitor_ip', '' );
		}

		// Prefer public IPs.
		foreach ( $valid as $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return (string) apply_filters( 'burst_visitor_ip', $ip );
			}
		}

		return (string) apply_filters( 'burst_visitor_ip', $valid[0] );
	}

	/**
	 * Return all registered WordPress role names.
	 *
	 * @return array<string,string> Role slug → display name.
	 */
	private function get_user_roles(): array {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return [];
		}

		return $wp_roles->get_names();
	}
}
