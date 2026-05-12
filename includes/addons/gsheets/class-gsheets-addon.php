<?php
/**
 * Class GSheets_Addon
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use WP_Error;
use PBAPI;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once 'class-gsheets-post-bridge.php';
require_once 'hooks.php';

/**
 * Google Sheets addon.
 */
class GSheets_Addon extends Addon {

	/**
	 * Handles the addon name.
	 *
	 * @var string
	 */
	public const TITLE = 'Google Sheets';

	/**
	 * Handles the addon's API name.
	 *
	 * @var string
	 */
	public const NAME = 'gsheets';

	/**
	 * Handles the addom's custom relation class.
	 *
	 * @var string
	 */
	public const BRIDGE = '\POSTS_BRIDGE\GSheets_Post_Bridge';

	/**
	 * Performs a request against the backend to check the connexion status.
	 *
	 * @param string $backend Backend name.
	 *
	 * @return boolean
	 */
	public function ping( $backend ) {
		$backend = PBAPI::get_backend( $backend );
		if ( ! $backend ) {
			Logger::log( "Google Sheets backend ping error: Unknown backend {$backend}", Logger::ERROR );
			return false;
		}

		$credential = $backend->credential;
		if ( ! $credential ) {
			Logger::log( 'Google Sheets backend ping error: Backend has no valid credential', Logger::ERROR );
			return false;
		}

		$parsed = wp_parse_url( $backend->base_url );
		$host   = $parsed['host'] ?? '';

		if ( 'sheets.googleapis.com' !== $host ) {
			Logger::log( 'Google Sheets backend ping error: Backend does not point to the Google Sheets API endpoints', Logger::ERROR );
			return false;
		}

		$access_token = $credential->get_access_token();

		if ( ! $access_token ) {
			Logger::log( 'Google Sheets backend ping error: Unable to recover the credential access token', Logger::ERROR );
			return false;
		}

		return true;
	}

	/**
	 * Performs a GET request against the backend endpoint and retrive the response data.
	 *
	 * @param string $endpoint Concatenation of spreadsheet ID and tab name.
	 * @param string $backend Backend name.
	 *
	 * @return array|WP_Error
	 */
	public function fetch( $endpoint, $backend ) {
		$backend = PBAPI::get_backend( $backend );
		if ( ! $backend ) {
			return new WP_Error( 'invalid_backend', 'Backend is unknown', array( 'backend' => $backend ) );
		}

		$credential = $backend->credential;
		if ( ! $credential ) {
			return new WP_Error( 'invalid_credential', 'The backend has no valid credential', (array) $backend->data() );
		}

		$access_token = $credential->get_access_token();
		if ( ! $access_token ) {
			return new WP_Error( 'invalid_credential', 'Unable to get the credential access token' );
		}

		$response = http_bridge_get(
			'https://www.googleapis.com/drive/v3/files',
			array( 'q' => "mimeType = 'application/vnd.google-apps.spreadsheet'" ),
			array(
				'Authorization' => "Bearer {$access_token}",
				'Accept'        => 'application/json',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Performs an introspection of the backend API and returns a list of available endpoints.
	 *
	 * @param string      $backend Target backend name.
	 * @param string|null $method HTTP method.
	 *
	 * @return array|WP_Error
	 */
	public function get_endpoints( $backend, $method = null ) {
		$response = $this->fetch( null, $backend );

		if ( is_wp_error( $response ) || empty( $response['data']['files'] ) ) {
			Logger::log( 'Google Sheets get endpoints error: Introspection error response', Logger::ERROR );
			Logger::log( $response, Logger::ERROR );
			return array();
		}

		return array_map(
			function ( $file ) {
				return '/v4/spreadsheets/' . $file['id'];
			},
			$response['data']['files']
		);
	}

	/**
	 * Performs an introspection of the backend endpoint and returns API fields
	 * and accepted content type.
	 *
	 * @param string      $endpoint Concatenation of spreadsheet ID and tab name.
	 * @param string      $backend Backend name.
	 * @param string|null $method HTTP method.
	 *
	 * @return array List of fields and content type of the endpoint.
	 */
	public function get_endpoint_schema( $endpoint, $backend, $method = null ) {
		$bridges = PBAPI::get_addon_bridges( self::NAME );
		foreach ( $bridges as $candidate ) {
			$data = $candidate->data();
			if ( ! $data ) {
				continue;
			}

			if ( $data['backend'] === $backend ) {
				/**
				* Found bridge.
				*
				* @var GSheets_Post_Bridge
				*/
				$bridge = $candidate;
			}
		}

		if ( ! isset( $bridge ) ) {
			Logger::log( 'Google Sheets get endpoint schema error: Unkown bridge', Logger::ERROR );
			return array();
		}

		$headers = $bridge->get_headers();

		if ( is_wp_error( $headers ) ) {
			Logger::log( 'Google Sheets get endpoint schema error: Introspection error response', Logger::ERROR );
			Logger::log( $headers, Logger::ERROR );
			return array();
		}

		$fields = array();
		foreach ( $headers as $header ) {
			$fields[] = array(
				'name'   => $header,
				'schema' => array( 'type' => 'string' ),
			);
		}

		return $fields;
	}

	/**
	 * Gets expiration time for introspection cache based on the introspection
	 * method.
	 *
	 * @param string $method Introspection method (ping, endpoints, schema).
	 *
	 * @return int Time in seconds.
	 */
	public function introspection_cache_expiration( $method ) {
		if ( Logger::is_active() ) {
			return 0;
		}

		switch ( $method ) {
			case 'ping':
				return 60 * 10;
			case 'endpoints':
			case 'schema':
				return 60 * 5;
			default:
				return 0;
		}
	}
}

GSheets_Addon::setup();
