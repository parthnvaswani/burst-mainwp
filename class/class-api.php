<?php
/**
 * Burst Statistics – MainWP API
 *
 * Handles all communication between the MainWP dashboard and child sites.
 *
 * Responsibility split:
 *   • `get_site_data()`  – read MainWP DB (or fall back to $wpdb).
 *   • `get_child_auth()` – fetch a signed auth token + nonce from the child's
 *                          REST endpoint.  Must be called during a full WP page
 *                          load so MainWP signing classes are available.
 *
 * The token and nonce are then forwarded to React via `wp_localize_script` so
 * that `apiFetch` can call the child's REST API directly from the browser.
 *
 * @package Burst_Statistics_MainWP
 */

namespace BurstMainWP;

defined( 'ABSPATH' ) || exit;

class API {

	private static ?self $instance = null;

	/**
	 * Stores last child-auth debug data keyed by site ID.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $last_auth_debug = [];

	/**
	 * Singleton instance accessor.
	 *
	 * @return self Singleton instance of the API class.
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
		// No hooks — this class is called explicitly during page render.
	}

	// ── Site Data ─────────────────────────────────────────────────────────────

	/**
	 * Retrieve a MainWP website row by ID.
	 *
	 * Prefers the official MainWP_DB class; falls back to a raw `$wpdb` query
	 * when it is unavailable (e.g. unit-test context).
	 *
	 * @param int $site_id MainWP site (wp) ID.
	 * @return object|null Database row or null when not found.
	 */
	public function get_site_data( int $site_id ): ?object {
		if ( class_exists( 'MainWP\Dashboard\MainWP_DB' ) ) {
			$site = \MainWP\Dashboard\MainWP_DB::instance()->get_website_by_id( $site_id );
			return $site ?: null;
		}

		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mainwp_wp WHERE id = %d", $site_id )
		) ?: null;
	}

	/**
	 * Get the last auth debug payload for a site.
	 *
	 * @param int $site_id MainWP site ID.
	 * @return array<string,mixed>
	 */
	public function get_last_auth_debug( int $site_id ): array {
		return $this->last_auth_debug[ $site_id ] ?? [];
	}

	// ── Auth / Token Exchange ─────────────────────────────────────────────────

	/**
	 * Fetch a REST auth token and nonce from the child site.
	 *
	 * Flow:
	 *  1. Load site data (private key, admin username, URL).
	 *  2. Build a signed request body using MainWP's signing infrastructure.
	 *  3. POST to the child's `burst/v1/mainwp-auth` endpoint.
	 *  4. Validate the response and return the structured auth array.
	 *
	 * The result is intentionally not cached across requests — nonces are
	 * single-use and the page load that needs them is infrequent.
	 *
	 * @param int $site_id MainWP site ID.
	 * @return array{nonce:string,token:string,root_url:string,capabilities:array,options:array,extra:array}|false
	 *         Auth payload on success, false on any failure.
	 */
	public function get_child_auth( int $site_id ): array|false {
		$debug = [
			'site_id'       => $site_id,
			'step'          => 'bootstrap',
			'reason'        => 'unknown',
			'http_attempts' => [],
		];

		if ( ! class_exists( 'MainWP\Dashboard\MainWP_Connect' ) ) {
			$debug['step']   = 'bootstrap';
			$debug['reason'] = 'mainwp_connect_class_missing';
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$debug['step'] = 'load_site_data';
		$site_data     = $this->get_site_data( $site_id );
		if ( ! $site_data ) {
			$debug['reason'] = 'site_data_not_found';
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$debug['step'] = 'build_signed_body';
		$body          = $this->build_signed_body( $site_data, 'burst_proxy' );
		if ( ! $body ) {
			$debug['reason'] = 'signed_body_generation_failed';
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$endpoint_base = trailingslashit( $site_data->url );
		$endpoints     = [
			$endpoint_base . 'wp-json/burst/v1/mainwp-auth',
			$endpoint_base . '?rest_route=/burst/v1/mainwp-auth',
		];

		$response = null;

		$debug['step'] = 'request_child_auth';
		foreach ( $endpoints as $endpoint ) {
			$attempt = [
				'endpoint' => $endpoint,
			];

			$response = wp_remote_post(
				$endpoint,
				[
					'headers' => [
						'Content-Type'  => 'application/json',
						'X-BURSTMAINWP' => '1',
					],
					'body'    => wp_json_encode( $body ),
					'timeout' => 15,
				]
			);

			if ( is_wp_error( $response ) ) {
				$attempt['result']        = 'wp_error';
				$attempt['code']          = $response->get_error_code();
				$attempt['message']       = $response->get_error_message();
				$debug['http_attempts'][] = $attempt;
				continue;
			}

			$http_code                = (int) wp_remote_retrieve_response_code( $response );
			$attempt['result']        = 'http';
			$attempt['http_code']     = $http_code;
			$body_excerpt             = substr( wp_remote_retrieve_body( $response ), 0, 220 );
			$attempt['body_excerpt']  = $body_excerpt;
			$debug['http_attempts'][] = $attempt;

			if ( 200 === $http_code ) {
				break;
			}
		}

		if ( is_wp_error( $response ) ) {
			$debug['reason'] = 'all_endpoints_returned_wp_error';
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$debug['reason']    = 'auth_endpoint_non_200_response';
			$debug['http_code'] = (int) wp_remote_retrieve_response_code( $response );
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$debug['step'] = 'parse_response';
		$data          = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			$debug['reason'] = 'invalid_json_response';
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		if ( ! $this->is_valid_auth_response( $data ) ) {
			$missing = [];
			foreach ( [ 'token', 'root_url', 'localization_data' ] as $required_key ) {
				if ( empty( $data[ $required_key ] ) ) {
					$missing[] = $required_key;
				}
			}

			$debug['reason']       = 'invalid_auth_payload';
			$debug['missing_keys'] = $missing;
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$debug['step']   = 'completed';
		$debug['reason'] = 'success';
		$this->set_last_auth_debug( $site_id, $debug );

		return $data;
	}

	/**
	 * Persist auth debug payload for later reporting.
	 */
	private function set_last_auth_debug( int $site_id, array $debug ): void {
		$this->last_auth_debug[ $site_id ] = $debug;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Validate that an auth response contains all required fields.
	 *
	 * @param mixed $data Decoded JSON from the child endpoint.
	 * @return bool True if the response is valid, false otherwise.
	 */
	private function is_valid_auth_response( mixed $data ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( [ 'token', 'root_url', 'localization_data' ] as $key ) {
			if ( empty( $data[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build a signed request body using MainWP's asymmetric signing infrastructure.
	 *
	 * @param object $site_data MainWP website row (must include `privkey`, `adminname`, `id`).
	 * @param string $_function  MainWP function name used as part of the signing payload.
	 * @param array  $extra     Additional fields merged into the body.
	 * @return array<string,mixed>|false Signed body ready to JSON-encode, or false on failure.
	 */
	private function build_signed_body( object $site_data, string $_function, array $extra = [] ): array|false {
		$nonce            = wp_rand( 0, 9999 );
		$sign_payload     = $_function . $nonce;
		$signed           = $this->sign_payload( $sign_payload, $site_data );
		$dashboard_origin = $this->get_dashboard_origin();

		if ( ! $signed ) {
			return false;
		}

		return array_merge(
			[
				'user'             => $site_data->adminname,
				'nonce'            => $nonce,
				'mainwpsignature'  => $signed['signature'],
				'function'         => $_function,
				'verifylib'        => $signed['use_seclib'] ? 1 : 0,
				'dashboard_origin' => $dashboard_origin,
			],
			$extra
		);
	}

	/**
	 * Build the dashboard site origin used by child-side CORS allowlisting.
	 */
	private function get_dashboard_origin(): string {
		$home  = home_url( '/' );
		$parts = wp_parse_url( $home );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$origin = strtolower( $parts['scheme'] . '://' . $parts['host'] );
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}

		return $origin;
	}

	/**
	 * Sign a string payload using whichever signing library MainWP has configured.
	 *
	 * MainWP supports both OpenSSL and a phpseclib-based fallback.  This method
	 * detects which one to use by consulting MainWP_Connect_Lib::is_use_fallback_sec_lib().
	 *
	 * @param string $payload   The raw string to sign (typically `$_function . $nonce`).
	 * @param object $site_data MainWP website row (must include `privkey`).
	 * @return array{signature:string,use_seclib:bool}|false Encoded signature, or false on failure.
	 */
	private function sign_payload( string $payload, object $site_data ): array|false {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw_privkey = base64_decode( $site_data->privkey, true );
		if ( false === $raw_privkey ) {
			return false;
		}

		$signature    = '';
		$sign_success = false;
		$use_seclib   = false;

		if (
			class_exists( 'MainWP\Dashboard\MainWP_Connect_Lib' ) &&
			\MainWP\Dashboard\MainWP_Connect_Lib::is_use_fallback_sec_lib( $site_data )
		) {
			$sign_success = \MainWP\Dashboard\MainWP_Connect_Lib::connect_sign(
				$payload,
				$signature,
				$raw_privkey,
				$site_data->id
			);
			$use_seclib   = true;
		} else {
			$alg          = \MainWP\Dashboard\MainWP_System_Utility::get_connect_sign_algorithm( $site_data );
			$sign_success = \MainWP\Dashboard\MainWP_Connect::connect_sign(
				$payload,
				$signature,
				$raw_privkey,
				$alg,
				$site_data->id
			);
		}

		if ( ! $sign_success || '' === $signature ) {
			return false;
		}

		return [
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'signature'  => base64_encode( $signature ),
			'use_seclib' => $use_seclib,
		];
	}
}
