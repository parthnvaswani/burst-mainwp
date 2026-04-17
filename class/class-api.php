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
		if ( ! class_exists( 'MainWP\Dashboard\MainWP_Connect' ) ) {
			return false;
		}

		$site_data = $this->get_site_data( $site_id );
		if ( ! $site_data ) {
			return false;
		}

		$body = $this->build_signed_body( $site_data, 'burst_proxy' );
		if ( ! $body ) {
			return false;
		}

		$endpoint = trailingslashit( $site_data->url ) . 'wp-json/burst/v1/mainwp-auth';

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
			$this->debug_log( 'get_child_auth WP_Error: ' . $response->get_error_message() );
			return false;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$this->debug_log( sprintf( 'get_child_auth unexpected HTTP %d from %s', $http_code, $endpoint ) );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! $this->is_valid_auth_response( $data ) ) {
			$this->debug_log( 'get_child_auth: invalid or incomplete auth response.' );
			return false;
		}

		return $data;
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
		$nonce        = wp_rand( 0, 9999 );
		$sign_payload = $_function . $nonce;
		$signed       = $this->sign_payload( $sign_payload, $site_data );

		if ( ! $signed ) {
			return false;
		}

		return array_merge(
			[
				'user'            => $site_data->adminname,
				'nonce'           => $nonce,
				'mainwpsignature' => $signed['signature'],
				'function'        => $_function,
				'verifylib'       => $signed['use_seclib'] ? 1 : 0,
			],
			$extra
		);
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
			$msg = openssl_error_string();
			while ( $msg ) {
				$this->debug_log( 'OpenSSL signing error: ' . $msg );
			}
			return false;
		}

		return [
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'signature'  => base64_encode( $signature ),
			'use_seclib' => $use_seclib,
		];
	}

	/**
	 * Write a debug message to the PHP error log, but only when WP_DEBUG is on.
	 *
	 * @param string $message Human-readable message.
	 */
	private function debug_log( string $message ): void {
		unset( $message );
	}
}
