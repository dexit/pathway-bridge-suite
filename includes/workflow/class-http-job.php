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
 * Standard job to perform HTTP requests.
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
			return $payload; // Skip or error? Usually skip with log.
		}

		$method   = strtoupper( $config['method'] ?? 'POST' );
		$endpoint = $config['endpoint'] ?? '/';
		$headers  = $config['headers'] ?? array();
		$params   = $config['params'] ?? array();

		// Replace placeholders in endpoint, headers and params
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

		// Optionally merge response back into payload
		if ( ! empty( $config['response_field'] ) ) {
			$payload[ $config['response_field'] ] = $response['data'] ?? $response['body'];
		}

		return $payload;
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
			return $payload[ $key ] ?? $matches[0];
		}, $input );
	}
}
