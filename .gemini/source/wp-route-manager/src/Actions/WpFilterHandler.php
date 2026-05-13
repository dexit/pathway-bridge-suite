<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Actions;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class WpFilterHandler extends AbstractHandler {

	public function handle( int $endpoint_id, array $data, WP_REST_Request $request ): mixed {
		$hook = (string) get_post_meta( $endpoint_id, 'wprm_action_hook', true );

		if ( empty( $hook ) ) {
			return new \WP_Error( 'wprm_no_hook', __( 'No filter hook configured.', 'wp-route-manager' ), [ 'status' => 500 ] );
		}

		$result = apply_filters( $hook, $data, $request, $endpoint_id );

		$this->output = sprintf( 'apply_filters(%s) returned %s', $hook, gettype( $result ) );

		return $result;
	}
}
