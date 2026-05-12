<?php
/**
 * Class Airtable_Addon
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use PBAPI;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once 'class-airtable-post-bridge.php';
require_once 'hooks.php';

/**
 * Airtable addon class.
 */
class Airtable_Addon extends Addon {

	/**
	 * Handles the addon's title.
	 *
	 * @var string
	 */
	const TITLE = 'Airtable';

	/**
	 * Handles the addon's name.
	 *
	 * @var string
	 */
	const NAME = 'airtable';

	/**
	 * Handles the addon's custom bridge class.
	 *
	 * @var string
	 */
	const BRIDGE = '\POSTS_BRIDGE\Airtable_Post_Bridge';

	/**
	 * Performs a request against the backend to check the connection status.
	 *
	 * @param string $backend Backend name.
	 *
	 * @return boolean
	 */
	public function ping( $backend ) {
		$backend = PBAPI::get_backend( $backend );

		if ( ! $backend ) {
			Logger::log( 'Airtable backend ping error: Backend is unkown or invalid', Logger::ERROR );
			return false;
		}

		$response = $backend->get( '/v0/meta/bases' );
		if ( is_wp_error( $response ) ) {
			Logger::log( 'Airtable backend ping error: Unable to list airtable bases', Logger::ERROR );
			return false;
		}

		return true;
	}

	/**
	 * Performs a GET request against the backend endpoint and retrive the response data.
	 *
	 * @param string $endpoint Airtable endpoint.
	 * @param string $backend Backend name.
	 *
	 * @return array|WP_Error
	 */
	public function fetch( $endpoint, $backend ) {
		$backend = PBAPI::get_backend( $backend );
		if ( ! $backend ) {
			return new WP_Error( 'invalid_backend', 'Backend is unkown or invalid', array( 'backend' => $backend ) );
		}

		if ( $endpoint && '/v0/meta/tables' !== $endpoint ) {
			return $backend->get( $endpoint );
		}

		$response = $backend->get( '/v0/meta/bases' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$tables = array();
		foreach ( $response['data']['bases'] as $base ) {
			$schema_response = $backend->get( "/v0/meta/bases/{$base['id']}/tables" );

			if ( is_wp_error( $schema_response ) ) {
				return $schema_response;
			}

			foreach ( $schema_response['data']['tables'] as $table ) {
				$tables[] = array(
					'base_id'   => $base['id'],
					'base_name' => $base['name'],
					'label'     => "{$base['name']}/{$table['name']}",
					'name'      => $table['name'],
					'id'        => $table['id'],
					'endpoint'  => "/v0/{$base['id']}/{$table['name']}",
				);
			}
		}

		return array( 'data' => array( 'tables' => $tables ) );
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

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$endpoints = array();
		foreach ( $response['data']['tables'] as $table ) {
			$endpoints[] = $table['endpoint'];
		}

		return $endpoints;
	}

	/**
	 * Performs an introspection of the backend endpoint and returns API fields
	 * and accepted content type.
	 *
	 * @param string      $endpoint Airtable endpoint.
	 * @param string      $backend Backend name.
	 * @param string|null $method HTTP method.
	 *
	 * @return array List of fields and content type of the endpoint.
	 */
	public function get_endpoint_schema( $endpoint, $backend, $method = null ) {
		$bridge = new Airtable_Post_Bridge(
			array(
				'method'   => 'GET',
				'backend'  => $backend,
				'endpoint' => $endpoint,
			)
		);

		$fields = $bridge->get_fields();

		if ( is_wp_error( $fields ) ) {
			return array();
		}

		return OpenAPI::expand_fields_schema( $fields );
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
				return 60 * 60 * 24;
			case 'endpoints':
				return 60 * 10;
			case 'schema':
				return 60 * 5;
			default:
				return 0;
		}
	}
}

Airtable_Addon::setup();
