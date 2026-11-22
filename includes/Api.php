<?php
/**
 * Client for the SmokyPost service (www.smokypost.com).
 *
 * @package SmokyPost
 */

namespace SmokyPost;

defined( 'ABSPATH' ) || exit;

/**
 * Every call returns the decoded body or a WP_Error whose code is the API's
 * error code (e.g. "quota_exceeded") and whose message is already translated
 * by the service.
 */
final class Api {

	const TOKEN_OPTION = 'smokypost_site_token';

	/**
	 * Base URL. Can be changed with the SMOKYPOST_API_URL constant (testing).
	 */
	public static function base_url() {
		$url = defined( 'SMOKYPOST_API_URL' ) ? SMOKYPOST_API_URL : 'https://www.smokypost.com/v1';
		return untrailingslashit( $url );
	}

	public static function is_connected() {
		return '' !== self::token();
	}

	public static function token() {
		return (string) get_option( self::TOKEN_OPTION, '' );
	}

	/**
	 * @param string $token Site token.
	 */
	public static function save_token( $token ) {
		update_option( self::TOKEN_OPTION, (string) $token, false );
		delete_transient( 'smokypost_site' );
	}

	public static function forget_token() {
		delete_option( self::TOKEN_OPTION );
		delete_transient( 'smokypost_site' );
	}

	/**
	 * Details about this site that the service needs when it connects.
	 *
	 * @return array<string, string>
	 */
	public static function site_info() {
		return array(
			'home_url'       => home_url( '/' ),
			'plugin_version' => SMOKYPOST_VERSION,
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'locale'         => get_locale(),
			'timezone'       => wp_timezone_string(),
		);
	}

	/**
	 * @param string               $method GET, POST, PUT or DELETE.
	 * @param string               $path   Path below /v1, e.g. "/site".
	 * @param array<string, mixed> $data   Query (GET) or JSON body.
	 * @param bool                 $auth   Send the site token.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function request( $method, $path, array $data = array(), $auth = true ) {
		$url     = self::base_url() . $path;
		$headers = array(
			'Accept'                     => 'application/json',
			'Accept-Language'            => str_replace( '_', '-', determine_locale() ),
			'X-SmokyPost-Plugin-Version' => SMOKYPOST_VERSION,
			'X-SmokyPost-WP-Version'     => get_bloginfo( 'version' ),
			'X-SmokyPost-PHP-Version'    => PHP_VERSION,
			'X-SmokyPost-Timezone'       => wp_timezone_string(),
		);
		if ( $auth ) {
			if ( ! self::is_connected() ) {
				return new \WP_Error( 'not_connected', __( 'This site is not connected to SmokyPost yet.', 'smokypost' ) );
			}
			$headers['Authorization']     = 'Bearer ' . self::token();
			$headers['X-SmokyPost-Token'] = self::token();
		}
		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 20,
		);
		if ( 'GET' === $method ) {
			if ( $data ) {
				$url = add_query_arg( array_map( 'rawurlencode', $data ), $url );
			}
		} else {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $data ? $data : new \stdClass() );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'unreachable', __( 'SmokyPost could not be reached. Please try again in a few minutes.', 'smokypost' ), array( 'detail' => $response->get_error_message() ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		$min = wp_remote_retrieve_header( $response, 'x-smokypost-min-plugin-version' );
		if ( $min && version_compare( SMOKYPOST_VERSION, (string) $min, '<' ) ) {
			update_option( 'smokypost_update_required', (string) $min, false );
		} else {
			delete_option( 'smokypost_update_required' );
		}

		if ( $status >= 400 || ! is_array( $body ) ) {
			$error = is_array( $body ) && isset( $body['error'] ) ? $body['error'] : array();
			$code  = isset( $error['code'] ) ? (string) $error['code'] : 'http_' . $status;
			if ( 'site_token_invalid' === $code && $auth ) {
				// The site was removed in the account panel or the account was deleted.
				self::forget_token();
			}
			return new \WP_Error(
				$code,
				isset( $error['message'] ) ? (string) $error['message'] : __( 'Something went wrong. Please try again.', 'smokypost' ),
				array(
					'status'  => $status,
					'details' => isset( $error['details'] ) ? (array) $error['details'] : array(),
				)
			);
		}
		return $body;
	}

	/**
	 * Plan, quota, connections and warnings, cached for a few minutes.
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function site( $fresh = false ) {
		if ( ! $fresh ) {
			$cached = get_transient( 'smokypost_site' );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$site = self::request( 'GET', '/site' );
		if ( ! is_wp_error( $site ) ) {
			set_transient( 'smokypost_site', $site, 5 * MINUTE_IN_SECONDS );
		}
		return $site;
	}

	/**
	 * Selected account per platform, from the cached site data.
	 *
	 * @return array<string, array<string, mixed>> platform => target
	 */
	public static function connected_targets() {
		$site = self::is_connected() ? self::site() : null;
		$out  = array();
		if ( is_array( $site ) && ! empty( $site['connections'] ) ) {
			foreach ( $site['connections'] as $c ) {
				if ( ! empty( $c['target'] ) && ! empty( $c['in_plan'] ) ) {
					$out[ $c['platform'] ] = $c['target'];
				}
			}
		}
		return $out;
	}

	/**
	 * @param string $feature Feature slug, e.g. "scheduling".
	 */
	public static function has_feature( $feature ) {
		$site = self::is_connected() ? self::site() : null;
		return is_array( $site ) && in_array( $feature, (array) ( $site['license']['plan']['features'] ?? array() ), true );
	}
}
