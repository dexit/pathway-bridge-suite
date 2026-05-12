<?php
/**
 * REST Workflow Job
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Workflow;

use PATHWAY_BRIDGE_SUITE\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Standard job to perform internal REST requests.
 */
class REST_Job {

	public static function run( $payload, $bridge, $job ) {
		$config = $job->data;
		$route  = $config['route'] ?? '';
		$method = $config['method'] ?? 'POST';

		if ( empty( $route ) ) {
			return $payload;
		}

		$request = new \WP_REST_Request( $method, $route );
		$request->set_query_params( $payload );
		$request->set_body_params( $payload );

		$response = rest_do_request( $request );
		$data = rest_get_server()->response_to_data( $response, true );

		if ( $response->is_error() ) {
			Logger::log( "Internal REST request failed: " . $route, Logger::ERROR );
		}

		if ( ! empty( $config['response_field'] ) ) {
			$payload[ $config['response_field'] ] = $data;
		}

		return $payload;
	}
}
