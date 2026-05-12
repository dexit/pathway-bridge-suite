<?php
/**
 * Class Holded_Addon
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use PBAPI;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once 'class-holded-post-bridge.php';
require_once 'hooks.php';

/**
 * REST API Addon class.
 */
class Holded_Addon extends Addon {

	/**
	 * Holds the addon's title.
	 *
	 * @var string
	 */
	public const TITLE = 'Holded';

	/**
	 * Holds the addon's name.
	 *
	 * @var string
	 */
	public const NAME = 'holded';

	/**
	 * Holds the addom's custom bridge class.
	 *
	 * @var string
	 */
	public const BRIDGE = '\POSTS_BRIDGE\Holded_Post_Bridge';

	/**
	 * Holds the OAS endpoints base URL.
	 *
	 * @var string
	 */
	public const OAS_BASE_URL = 'https://developers.holded.com/holded/api-next';

	/**
	 * Holds the addon OAS URLs map.
	 *
	 * @var array
	 */
	public const OAS_URLS = array(
		'invoicing'  => '/v2/branches/1.0/reference/list-contacts-1',
		'crm'        => '/v2/branches/1.0/reference/list-leads-1',
		'projects'   => '/v2/branches/1.0/reference/list-projects',
		'team'       => '/v2/branches/1.0/reference/listemployees',
		'accounting' => '/v2/branches/1.0/reference/listdailyledger',
	);

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
			Logger::log( "Backend {$backend} is unknown", Logger::ERROR );
			return false;
		}

		$response = $backend->get( '/api/invoicing/v1/contacts', array( 'limit' => 1 ) );
		if ( is_wp_error( $response ) ) {
			Logger::log( 'Holded backend ping error response', Logger::ERROR );
			Logger::log( $response, Logger::ERROR );
			return false;
		}

		return true;
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
		$paths = array();

		foreach ( self::OAS_URLS as $module => $oas_path ) {
			$oas_url = self::OAS_BASE_URL . $oas_path . '?dereference=false&reduce=false';

			$response = wp_remote_get(
				$oas_url,
				array(
					'headers' => array(
						'Accept'     => 'application/json',
						'Host'       => 'developers.holded.com',
						'Alt-Used'   => 'developers.holded.com',
						'Referer'    => 'https://developers.holded.com/reference/list-contacts-1',
						'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:144.0) Gecko/20100101 Firefox/144.0',
					),
				),
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$data = json_decode( $response['body'], true );
			if ( ! $data ) {
				continue;
			}

			$oa_explorer = new OpenAPI( $data['data']['api']['schema'] );

			$module_paths = array();
			foreach ( $oa_explorer->paths() as $path ) {
				if ( preg_match_all( '/{[^}]+}/', $path, $matches ) ) {
					if ( '{docType}' !== $matches[0][0] || 1 < count( $matches[0] ) ) {
						continue;
					}
				}

				$path_obj = $oa_explorer->path_obj( $path );

				if ( $path_obj && isset( $path_obj['get'] ) ) {
					$module_paths[] = $path;
				}
			}

			$module_paths = array_map(
				function ( $path ) use ( $module ) {
					return '/api/' . $module . '/v1' . $path;
				},
				$module_paths,
			);

			$paths = array_merge( $paths, $module_paths );
		}

		return $paths;
	}

	/**
	 * Performs an introspection of the backend endpoint and returns API fields
	 * and accepted content type.
	 *
	 * @param string      $endpoint API endpoint.
	 * @param string      $backend Backend name.
	 * @param string|null $method HTTP method.
	 *
	 * @return array List of fields and content type of the endpoint.
	 */
	public function get_endpoint_schema( $endpoint, $backend, $method = null ) {
		$chunks = array_values( array_filter( explode( '/', $endpoint ) ) );
		if ( empty( $chunks ) ) {
			return array();
		}

		$api_base = $chunks[0] ?? null;
		if ( 'api' !== $api_base ) {
			array_unshift( $chunks, 'api' );
		}

		$module   = $chunks[1] ?? null;
		$version  = $chunks[2] ?? null;
		$resource = $chunks[3] ?? null;

		if ( ! ( $module && $version && $resource ) ) {
			return array();
		}

		$oas_url = self::OAS_URLS[ $module ] ?? null;
		if ( ! $oas_url ) {
			return array();
		}

		$oas_url  = self::OAS_BASE_URL . $oas_url . '?dereference=false&reduce=false';
		$response = wp_remote_get(
			$oas_url,
			array(
				'headers' => array(
					'Accept'     => 'application/json',
					'Host'       => 'developers.holded.com',
					'Alt-Used'   => 'developers.holded.com',
					'Referer'    => 'https://developers.holded.com/reference/list-contacts-1',
					'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:144.0) Gecko/20100101 Firefox/144.0',
				),
			),
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$data = json_decode( $response['body'], true );
		if ( ! $data ) {
			return array();
		}

		$oa_explorer = new OpenAPI( $data['data']['api']['schema'] );

		$path   = preg_replace( '/\/api\/' . $module . '\/v\d+/', '', $endpoint );
		$params = $oa_explorer->response( $path, 'get' );

		if ( empty( $params ) ) {
			return array();
		}

		$fields = array();

		if ( 1 === count( $params ) ) {
			if ( 'array' === $params[0]['schema']['type'] && 'object' === $params[0]['schema']['items']['type'] ) {
				foreach ( $params[0]['schema']['items']['properties'] as $name => $schema ) {
					$fields[] = array(
						'name'   => $name,
						'schema' => $schema,
					);
				}
			}
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
			case 'endpoints':
			case 'schema':
				return 60 * 60 * 24;
			default:
				return 0;
		}
	}
}

Holded_Addon::setup();
