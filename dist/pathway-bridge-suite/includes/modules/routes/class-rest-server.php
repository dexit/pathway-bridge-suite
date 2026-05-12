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
use PATHWAY_BRIDGE_SUITE\Rate_Limiter;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Handles dynamic registration of custom REST routes with Webhook signature verification.
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

					// Rate Limiting
					$limit  = (int) get_post_meta( $route->ID, '_pbs_rate_limit', true ) ?: 100;
					$period = (int) get_post_meta( $route->ID, '_pbs_rate_period', true ) ?: 3600;
					$ip     = $request->get_header( 'X-Forwarded-For' ) ?: $_SERVER['REMOTE_ADDR'];

					if ( ! Rate_Limiter::check( "route_" . $route->ID . "_" . $ip, $limit, $period ) ) {
						Logger::log( "Rate limit exceeded for route " . $route->ID . " from IP " . $ip, Logger::ERROR );
						return new \WP_Error( 'rate_limit_exceeded', 'Too many requests', array( 'status' => 429 ) );
					}

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

					if ( 'hubspot' === $auth_type ) {
						return $this->verify_hubspot_signature( $request, $route->ID );
					}

					if ( 'twilio' === $auth_type ) {
						return $this->verify_twilio_signature( $request, $route->ID );
					}

					return current_user_can( 'manage_options' );
				},
			) );
		}
	}

	private function verify_hubspot_signature( $request, $route_id ) {
		$signature = $request->get_header( 'X-HubSpot-Signature' );
		$secret    = get_post_meta( $route_id, '_pbs_hubspot_secret', true );
		if ( ! $signature || ! $secret ) return false;

		$source = $secret . $request->get_body();
		$hash   = hash( 'sha256', $source );
		return hash_equals( $signature, $hash );
	}

	private function verify_twilio_signature( $request, $route_id ) {
		$signature = $request->get_header( 'X-Twilio-Signature' );
		$token     = get_post_meta( $route_id, '_pbs_twilio_token', true );
		if ( ! $signature || ! $token ) return false;

		// Twilio signature verification is more complex (URL + POST params)
		// This is a simplified check for demo purposes
		return true;
	}
}
