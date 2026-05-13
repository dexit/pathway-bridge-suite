<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Cli;

use WP_Route_Manager\PostTypes\EndpointPostType;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage WP Route Manager endpoints.
 *
 * ## EXAMPLES
 *
 *   wp wprm endpoint list
 *   wp wprm endpoint create --name="Ingest Orders" --route="orders/ingest" --method=POST --action-type=wp_action --action-hook=my_order_ingest
 *   wp wprm endpoint get 5
 *   wp wprm endpoint toggle 5
 *   wp wprm endpoint delete 5 --yes
 *
 * @package WP_Route_Manager
 */
class EndpointCommand {

	/**
	 * List all endpoints.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format. Options: table, json, csv, ids, count. Default: table.
	 *
	 * [--status=<status>]
	 * : Filter by status: active, inactive, all. Default: all.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm endpoint list
	 *   wp wprm endpoint list --format=json
	 *   wp wprm endpoint list --status=active
	 *
	 * @subcommand list
	 */
	public function list_cmd( array $args, array $assoc ): void {
		$status = $assoc['status'] ?? 'all';
		$format = $assoc['format'] ?? 'table';

		$post_status = match ( $status ) {
			'active'   => [ 'publish' ],
			'inactive' => [ 'draft' ],
			default    => [ 'publish', 'draft' ],
		};

		$posts = get_posts( [
			'post_type'      => EndpointPostType::POST_TYPE,
			'post_status'    => $post_status,
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		] );

		$ns  = get_option( 'wprm_namespace', 'wprm' );
		$ver = get_option( 'wprm_api_version', 'v1' );

		$rows = [];
		foreach ( $posts as $post ) {
			$slug = get_post_meta( $post->ID, 'wprm_route_slug', true );
			$rows[] = [
				'id'          => $post->ID,
				'name'        => $post->post_title,
				'route'       => '/' . $ns . '/' . $ver . '/' . $slug,
				'method'      => strtoupper( (string) get_post_meta( $post->ID, 'wprm_method', true ) ),
				'action_type' => get_post_meta( $post->ID, 'wprm_action_type', true ),
				'auth'        => get_post_meta( $post->ID, 'wprm_require_auth', true ) ? 'yes' : 'no',
				'status'      => $post->post_status === 'publish' ? 'active' : 'inactive',
			];
		}

		WP_CLI\Utils\format_items( $format, $rows, [ 'id', 'name', 'route', 'method', 'action_type', 'auth', 'status' ] );
	}

	/**
	 * Get a single endpoint's full details.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Endpoint post ID.
	 *
	 * [--format=<format>]
	 * : table or json. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm endpoint get 5
	 *   wp wprm endpoint get 5 --format=json
	 */
	public function get( array $args, array $assoc ): void {
		$id   = (int) ( $args[0] ?? 0 );
		$post = $this->get_endpoint_or_die( $id );

		$data = $this->endpoint_data( $post );
		$format = $assoc['format'] ?? 'table';

		if ( $format === 'json' ) {
			WP_CLI::line( wp_json_encode( $data, JSON_PRETTY_PRINT ) );
		} else {
			foreach ( $data as $k => $v ) {
				WP_CLI::line( sprintf( '%-25s %s', $k . ':', is_array( $v ) ? wp_json_encode( $v ) : $v ) );
			}
		}
	}

	/**
	 * Create a new endpoint.
	 *
	 * ## OPTIONS
	 *
	 * --name=<name>
	 * : Human-readable name.
	 *
	 * --route=<route>
	 * : Route slug, e.g. "my-endpoint" or "product/(?P<id>\d+)".
	 *
	 * [--method=<method>]
	 * : HTTP method. Default: POST.
	 *
	 * [--action-type=<type>]
	 * : wp_action, wp_filter, http_forward, php_snippet. Default: wp_action.
	 *
	 * [--action-hook=<hook>]
	 * : Hook name for wp_action / wp_filter.
	 *
	 * [--forward-url=<url>]
	 * : Target URL for http_forward.
	 *
	 * [--snippet-id=<id>]
	 * : Snippet post ID for php_snippet.
	 *
	 * [--require-auth=<bool>]
	 * : Require API key? 1 or 0. Default: 1.
	 *
	 * [--activate]
	 * : Publish (activate) immediately. Default: creates as draft.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm endpoint create --name="Order Webhook" --route="orders/ingest" --method=POST --action-type=wp_action --action-hook=my_order_ingest --activate
	 */
	public function create( array $args, array $assoc ): void {
		$name   = $assoc['name'] ?? '';
		$route  = $assoc['route'] ?? '';

		if ( ! $name || ! $route ) {
			WP_CLI::error( '--name and --route are required.' );
		}

		$status = isset( $assoc['activate'] ) ? 'publish' : 'draft';

		$post_id = wp_insert_post( [
			'post_title'  => sanitize_text_field( $name ),
			'post_type'   => EndpointPostType::POST_TYPE,
			'post_status' => $status,
		], true );

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::error( $post_id->get_error_message() );
		}

		$meta_map = [
			'wprm_route_slug'    => sanitize_text_field( $route ),
			'wprm_method'        => strtoupper( $assoc['method'] ?? 'POST' ),
			'wprm_action_type'   => sanitize_key( $assoc['action-type'] ?? 'wp_action' ),
			'wprm_action_hook'   => sanitize_key( $assoc['action-hook'] ?? '' ),
			'wprm_forward_url'   => esc_url_raw( $assoc['forward-url'] ?? '' ),
			'wprm_action_snippet_id' => absint( $assoc['snippet-id'] ?? 0 ),
			'wprm_require_auth'  => isset( $assoc['require-auth'] ) ? (int) $assoc['require-auth'] : 1,
			'wprm_log_requests'  => 1,
		];

		foreach ( $meta_map as $key => $value ) {
			if ( $value !== '' && $value !== 0 ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		WP_CLI::success( sprintf( 'Endpoint created with ID %d (status: %s).', $post_id, $status ) );
	}

	/**
	 * Toggle an endpoint between active (publish) and inactive (draft).
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Endpoint post ID.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm endpoint toggle 5
	 */
	public function toggle( array $args, array $assoc ): void {
		$post  = $this->get_endpoint_or_die( (int) ( $args[0] ?? 0 ) );
		$new   = $post->post_status === 'publish' ? 'draft' : 'publish';
		wp_update_post( [ 'ID' => $post->ID, 'post_status' => $new ] );
		WP_CLI::success( sprintf( 'Endpoint #%d is now %s.', $post->ID, $new === 'publish' ? 'active' : 'inactive' ) );
	}

	/**
	 * Delete an endpoint permanently.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Endpoint post ID.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm endpoint delete 5 --yes
	 */
	public function delete( array $args, array $assoc ): void {
		$post = $this->get_endpoint_or_die( (int) ( $args[0] ?? 0 ) );
		WP_CLI::confirm( sprintf( 'Delete endpoint #%d "%s" permanently?', $post->ID, $post->post_title ), $assoc );
		wp_delete_post( $post->ID, true );
		WP_CLI::success( 'Endpoint deleted.' );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function get_endpoint_or_die( int $id ): \WP_Post {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || $post->post_type !== EndpointPostType::POST_TYPE ) {
			WP_CLI::error( "No endpoint found with ID {$id}." );
		}
		return $post;
	}

	/** @return array<string,mixed> */
	private function endpoint_data( \WP_Post $post ): array {
		$ns  = get_option( 'wprm_namespace', 'wprm' );
		$ver = get_option( 'wprm_api_version', 'v1' );
		$slug = get_post_meta( $post->ID, 'wprm_route_slug', true );

		return [
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'status'      => $post->post_status === 'publish' ? 'active' : 'inactive',
			'route_slug'  => $slug,
			'full_url'    => rest_url( $ns . '/' . $ver . '/' . $slug ),
			'method'      => strtoupper( (string) get_post_meta( $post->ID, 'wprm_method', true ) ),
			'require_auth' => (bool) get_post_meta( $post->ID, 'wprm_require_auth', true ),
			'action_type' => get_post_meta( $post->ID, 'wprm_action_type', true ),
			'action_hook' => get_post_meta( $post->ID, 'wprm_action_hook', true ),
			'forward_url' => get_post_meta( $post->ID, 'wprm_forward_url', true ),
			'parse_mode'  => get_post_meta( $post->ID, 'wprm_body_parse_mode', true ),
			'log_requests' => (bool) get_post_meta( $post->ID, 'wprm_log_requests', true ),
			'created'     => $post->post_date,
			'modified'    => $post->post_modified,
		];
	}
}
