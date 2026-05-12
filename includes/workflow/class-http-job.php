<?php
/**
 * HTTP Workflow Job
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Workflow;

use HTTP_BRIDGE\Backend;
use PATHWAY_BRIDGE_SUITE\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Standard job to perform HTTP requests with ODATA support.
 */
class HTTP_Job {

	/**
	 * Execute an HTTP request.
	 *
	 * @param array $payload Current workflow payload.
	 * @param mixed $bridge  The bridge triggering the workflow.
	 * @param Job   $job     The job instance.
	 *
	 * @return array|WP_Error
	 */
	public static function run( $payload, $bridge, $job ) {
		$config = $job->data;
		$backend_name = $config['backend'] ?? '';

		if ( empty( $backend_name ) ) {
			return $payload;
		}

		$backend = Backend::get( $backend_name );
		if ( ! $backend ) {
			Logger::log( "HTTP Backend not found: " . $backend_name, Logger::ERROR );
			return $payload;
		}

		$method   = strtoupper( $config['method'] ?? 'POST' );
		$endpoint = $config['endpoint'] ?? '/';
		$headers  = $config['headers'] ?? array();
		$params   = $config['params'] ?? array();

		// ODATA Handling
		if ( ! empty( $config['odata'] ) ) {
			$params = array_merge( $params, self::build_odata_query( $config['odata'], $payload ) );
		}

		// Replace placeholders
		$endpoint = self::replace_placeholders( $endpoint, $payload );
		$headers  = self::replace_placeholders( $headers, $payload );
		$params   = self::replace_placeholders( $params, $payload );

		Logger::log( "Executing HTTP request: [$method] $endpoint", Logger::INFO );

		if ( in_array( $method, array( 'GET', 'DELETE', 'HEAD' ) ) ) {
			$response = $backend->{strtolower($method)}( $endpoint, $params, $headers );
		} else {
			$data = $payload;
			if ( isset( $config['payload_field'] ) ) {
				$data = $payload[ $config['payload_field'] ] ?? array();
			}
			$response = $backend->{strtolower($method)}( $endpoint, $data, $headers );
		}

		if ( is_wp_error( $response ) ) {
			Logger::log( "HTTP Request failed: " . $response->get_error_message(), Logger::ERROR );
			return $response;
		}

		if ( ! empty( $config['response_field'] ) ) {
			$payload[ $config['response_field'] ] = $response['data'] ?? $response['body'];
		}

		return $payload;
	}

	private static function build_odata_query( $odata_config, $payload ) {
		$query = array();

		if ( ! empty( $odata_config['filter'] ) ) {
			$query['$filter'] = self::replace_placeholders( $odata_config['filter'], $payload );
		}

		if ( ! empty( $odata_config['select'] ) ) {
			$query['$select'] = $odata_config['select'];
		}

		if ( ! empty( $odata_config['expand'] ) ) {
			$query['$expand'] = $odata_config['expand'];
		}

		return $query;
	}

	/**
	 * Replace {field} placeholders with values from payload.
	 */
	private static function replace_placeholders( $input, $payload ) {
		if ( is_array( $input ) ) {
			foreach ( $input as &$value ) {
				$value = self::replace_placeholders( $value, $payload );
			}
			return $input;
		}

		if ( ! is_string( $input ) ) {
			return $input;
		}

		return preg_replace_callback( '/\{([^\}]+)\}/', function ( $matches ) use ( $payload ) {
			$key = $matches[1];
			$val = $payload[ $key ] ?? null;

			if ( is_array($val) ) return json_encode($val);
			return (string)($val ?? $matches[0]);
		}, $input );
	}
}
