<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Admin;

use WP_Route_Manager\Storage\PayloadQueue;
use WP_Route_Manager\PostTypes\EndpointPostType;

defined( 'ABSPATH' ) || exit;

final class QueueAdmin {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-route-manager' ) );
		}

		$queue = new PayloadQueue();

		$filter_status   = sanitize_key( $_GET['status'] ?? '' );
		$filter_endpoint = absint( $_GET['endpoint_id'] ?? 0 );

		$query_args = [ 'limit' => 50 ];
		if ( $filter_status )   $query_args['status']      = $filter_status;
		if ( $filter_endpoint ) $query_args['endpoint_id'] = $filter_endpoint;

		$result = $queue->query( $query_args );
		$counts = $queue->counts();

		$endpoints = get_posts( [
			'post_type'      => EndpointPostType::POST_TYPE,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		] );

		require WPRM_DIR . 'templates/admin-queue.php';
	}
}
