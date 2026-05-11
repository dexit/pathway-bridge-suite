<?php
/**
 * Dynamic REST Server for Routes Bridge
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Modules\Routes;

use WP_REST_Server;
use WP_REST_Request;

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
		) );

		foreach ( $routes as $route ) {
			$endpoint = get_post_meta( $route->ID, '_pbs_endpoint', true );
			$method   = get_post_meta( $route->ID, '_pbs_method', true ) ?: 'POST';

			if ( ! $endpoint ) continue;

			register_rest_route( 'pathway/v1', '/' . ltrim( $endpoint, '/' ), array(
				'methods'  => $method,
				'callback' => function ( WP_REST_Request $request ) use ( $route ) {
					$module = \PATHWAY_BRIDGE_SUITE\Registry::get_instance()->get( 'routes' );
					return $module->process_request( $request->get_params(), $route->ID );
				},
				'permission_callback' => '__return_true', // Configurable per route in future
			) );
		}
	}
}
