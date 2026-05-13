<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Admin;

use WP_Route_Manager\DB\ApiKeyRepository;
use WP_Route_Manager\PostTypes\EndpointPostType;

defined( 'ABSPATH' ) || exit;

final class ApiKeyAdmin {

	private ApiKeyRepository $repo;

	public function __construct() {
		$this->repo = new ApiKeyRepository();
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-route-manager' ) );
		}

		$keys = $this->repo->all();

		// All active endpoints for the "allowed endpoints" multi-select.
		$endpoints = get_posts( [
			'post_type'      => EndpointPostType::POST_TYPE,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		] );

		require WPRM_DIR . 'templates/admin-api-keys.php';
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public static function ajax_create(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-route-manager' ), 403 );
		}

		$label    = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
		$ep_ids   = array_map( 'absint', (array) ( $_POST['endpoint_ids'] ?? [] ) );
		$ep_ids   = array_filter( $ep_ids );

		if ( empty( $label ) ) {
			wp_send_json_error( __( 'Label is required.', 'wp-route-manager' ) );
		}

		$repo   = new ApiKeyRepository();
		$result = $repo->create( $label, $ep_ids );

		wp_send_json_success( [
			'id'        => $result['id'],
			'plain_key' => $result['plain_key'],
			'label'     => $label,
			'prefix'    => substr( $result['plain_key'], 0, 12 ) . '…',
		] );
	}

	public static function ajax_delete(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-route-manager' ), 403 );
		}

		$id = absint( $_POST['id'] ?? 0 );
		( new ApiKeyRepository() )->delete( $id );
		wp_send_json_success();
	}

	public static function ajax_toggle(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-route-manager' ), 403 );
		}

		$id = absint( $_POST['id'] ?? 0 );
		( new ApiKeyRepository() )->toggle_status( $id );
		wp_send_json_success();
	}
}
