<?php
/**
 * Dynamic REST Server for Routes Bridge
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Modules\Routes;

use WP_REST_Server;
use WP_REST_Request;
use PATHWAY_BRIDGE_SUITE\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Handles dynamic registration of custom REST routes.
 */
class REST_Server {

	public function register_routes() {
		$routes = get_posts( array(
			'post_type' => Routes_Module::POST_TYPE,
			'posts_per_page' => -1,
			'post_status' => 'publish',
		) );

		foreach ( $routes as $route ) {
			$endpoint = get_post_meta( $route->ID, '_pbs_endpoint', true );
			$method   = get_post_meta( $route->ID, '_pbs_method', true ) ?: 'POST';
			$namespace = get_post_meta( $route->ID, '_pbs_namespace', true ) ?: 'pathway/v1';

			if ( ! $endpoint ) continue;

			register_rest_route( $namespace, '/' . ltrim( $endpoint, '/' ), array(
				'methods'  => $method,
				'callback' => function ( WP_REST_Request $request ) use ( $route ) {
					Logger::log( "Incoming REST request for route: " . $route->post_title, Logger::INFO );

					$module = \PATHWAY_BRIDGE_SUITE\Registry::get_instance()->get( 'routes' );
					if ( ! $module ) {
						return new \WP_Error( 'module_not_found', 'Routes module not initialized', array( 'status' => 500 ) );
					}

					return $module->process_request( $request->get_params(), $route->ID );
				},
				'permission_callback' => function ( $request ) use ( $route ) {
					$auth_type = get_post_meta( $route->ID, '_pbs_auth_type', true ) ?: 'public';

					if ( 'public' === $auth_type ) {
						return true;
					}

					if ( 'api_key' === $auth_type ) {
						$key = $request->get_header( 'X-PBS-API-KEY' );
						$expected = get_post_meta( $route->ID, '_pbs_api_key', true );
						return $key === $expected;
					}

					return current_user_can( 'manage_options' );
				},
			) );
		}
	}
}
