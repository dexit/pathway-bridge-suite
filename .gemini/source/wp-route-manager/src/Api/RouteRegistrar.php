<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Api;

use WP_Route_Manager\PostTypes\EndpointPostType;

defined( 'ABSPATH' ) || exit;

final class RouteRegistrar {

	public function register(): void {
		if ( ! (bool) get_option( 'wprm_global_enabled', 1 ) ) {
			return;
		}

		$ns  = sanitize_key( get_option( 'wprm_namespace', 'wprm' ) );
		$ver = sanitize_key( get_option( 'wprm_api_version', 'v1' ) );

		$endpoints = get_posts( [
			'post_type'      => EndpointPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		] );

		foreach ( $endpoints as $endpoint_id ) {
			$this->register_endpoint( (int) $endpoint_id, $ns, $ver );
		}
	}

	private function register_endpoint( int $endpoint_id, string $ns, string $ver ): void {
		$slug   = (string) get_post_meta( $endpoint_id, 'wprm_route_slug', true );
		$method = strtoupper( (string) get_post_meta( $endpoint_id, 'wprm_method', true ) ) ?: 'POST';

		if ( empty( $slug ) ) {
			return;
		}

		$wp_method = $this->map_method( $method );

		$handler = new RequestHandler( $endpoint_id );

		register_rest_route(
			$ns . '/' . $ver,
			'/' . ltrim( $slug, '/' ),
			[
				'methods'             => $wp_method,
				'callback'            => [ $handler, 'handle' ],
				'permission_callback' => [ $handler, 'permission' ],
				'args'                => apply_filters(
					'wprm_endpoint_args',
					[],
					$endpoint_id
				),
			]
		);
	}

	private function map_method( string $method ): string {
		return match ( $method ) {
			'GET'    => \WP_REST_Server::READABLE,
			'POST'   => \WP_REST_Server::CREATABLE,
			'PUT'    => \WP_REST_Server::EDITABLE,
			'PATCH'  => 'PATCH',
			'DELETE' => \WP_REST_Server::DELETABLE,
			default  => \WP_REST_Server::ALLMETHODS,
		};
	}
}
