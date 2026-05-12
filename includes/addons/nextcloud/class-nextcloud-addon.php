<?php
/**
 * Class Nextcloud_Addon
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use PBAPI;
use SimpleXMLElement;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once 'class-nextcloud-post-bridge.php';
require_once 'hooks.php';

/**
 * Nextcloud Addon class.
 */
class Nextcloud_Addon extends Addon {

	/**
	 * Handles the addon's title.
	 *
	 * @var string
	 */
	const TITLE = 'Nextcloud';

	/**
	 * Handles the addon's name.
	 *
	 * @var string
	 */
	const NAME = 'nextcloud';

	/**
	 * Handles the addom's custom bridge class.
	 *
	 * @var string
	 */
	const BRIDGE = '\POSTS_BRIDGE\Nextcloud_Post_Bridge';

	/**
	 * Performs a request against the backend to check the connexion status.
	 *
	 * @param string $backend Target backend name.
	 *
	 * @return boolean
	 */
	public function ping( $backend ) {
		$backend = PBAPI::get_backend( $backend );

		if ( ! $backend ) {
			return false;
		}

		$credential = $backend->credential;
		if ( ! $credential || 'Basic' !== $credential->schema ) {
			return false;
		}

		$response = $backend->get( '/remote.php/dav/files/' . rawurlencode( $credential->client_id ) );

		if ( is_wp_error( $response ) ) {
			Logger::log( 'Nextcloud backend ping error response', Logger::ERROR );
			Logger::log( $response, Logger::ERROR );
			return false;
		}

		return true;
	}

	/**
	 * Performs a GET request against the backend model and retrive the response data.
	 *
	 * @param string $endpoint Target model name.
	 * @param string $backend Target backend name.
	 *
	 * @return array|WP_Error
	 */
	public function fetch( $endpoint, $backend ) {
		if ( ! class_exists( 'SimpleXMLElement' ) ) {
			return new WP_Error( 'xml_not_supported', 'Requires phpxml extension to be enabled' );
		}

		$backend = PBAPI::get_backend( $backend );
		if ( ! $backend ) {
			return new WP_Error( 'invalid_backend', 'Backend not found', array( 'backend' => $backend ) );
		}

		$credential = $backend->credential;
		if ( ! $credential || 'Basic' !== $credential->schema ) {
			return new WP_Error( 'invalid_backend', 'Backend has no credential', $backend->data() );
		}

		$authorization = $credential->authorization();
		if ( ! $authorization ) {
			return new WP_Error( 'invalid_credential', 'Credential has no authorization', $credential->data() );
		}

		$url = $backend->url( '/remote.php/dav/files/' . rawurlencode( $credential->client_id ) );

		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'PROPFIND',
				'headers' => array(
					'Depth'         => '5',
					'Authorization' => $authorization,
					'Content-Type'  => 'text/xml',
				),
				'body'    => '<?xml version="1.0" encoding="utf-8" ?>'
					. '<d:propfind xmlns:d="DAV:">'
						. '<d:prop><d:href/></d:prop>'
					. '</d:propfind>',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 300 <= $response['response']['code'] ) {
			$error = new WP_Error( 'bad_request', 'Can not conenct to the backend' );
			$error->add_data( array( 'response' => $response ) );
			return $error;
		}

		$xml = new SimpleXMLElement( $response['body'] );
		$xml->registerXPathNamespace( 'd', 'DAV:' );

		$parsed_url = wp_parse_url( $url );
		$basepath   = $parsed_url['path'] ?? '/';

		$files = array();
		foreach ( $xml->xpath( '//d:response' ) as $item ) {
			$href     = (string) $item->children( 'DAV:' )->href;
			$filepath = rawurldecode( str_replace( $basepath, '', $href ) );

			if ( '/' === $filepath ) {
				continue;
			}

			$pathinfo = pathinfo( $filepath );
			$is_file  = isset( $pathinfo['extension'] );

			if ( $is_file && 'csv' !== strtolower( $pathinfo['extension'] ) ) {
				continue;
			}

			if ( ! $is_file && 'files' === $endpoint ) {
				continue;
			} elseif ( $is_file && 'directories' === $endpoint ) {
				continue;
			}

			$files[] = array(
				'path'    => substr( $filepath, 1 ),
				'is_file' => $is_file,
			);
		}

		return array( 'data' => array( 'files' => $files ) );
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
		$response = $this->fetch( 'endpoints', $backend );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$endpoints = array();
		foreach ( $response['data']['files'] as $file ) {
			$endpoints[] = $file['path'];
		}

		return $endpoints;
	}

	/**
	 * Performs an introspection of the backend model and returns API fields
	 * and accepted content type.
	 *
	 * @param string      $filepath Filepath.
	 * @param string      $backend Backend name.
	 * @param string|null $method HTTP method.
	 *
	 * @return array List of fields and content type of the model.
	 */
	public function get_endpoint_schema( $filepath, $backend, $method = null ) {
		$bridge = new Nextcloud_Post_Bridge(
			array(
				'endpoint' => $filepath,
				'backend'  => $backend,
			)
		);

		$headers = $bridge->table_headers();
		if ( is_wp_error( $headers ) ) {
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

Nextcloud_Addon::setup();

add_filter(
	'http_request_args',
	function ( $args ) {
		$args['timeout'] = 30;
		return $args;
	}
);
