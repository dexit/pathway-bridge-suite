<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Actions;

use WP_REST_Request;
use WP_Route_Manager\Storage\PayloadQueue;

defined( 'ABSPATH' ) || exit;

/**
 * Store Payload Handler — saves incoming data to one or more destinations:
 *
 *   log          → wprm_logs (always available, existing)
 *   cpt          → saves as a WordPress Custom Post Type post
 *   queue        → wprm_payload_queue for deferred/chunked processing
 *   temp         → WordPress transient (short-term hold, expires)
 *
 * Multiple destinations can be active simultaneously.
 * Config fields (in action_config JSON):
 *   store_destinations  array   ['log','cpt','queue','temp']
 *   store_cpt_type      string  target post type slug
 *   store_cpt_title_key string  payload key to use as post title
 *   store_cpt_map       object  {meta_key: payload_key} mapping
 *   store_queue_status  string  'pending'|'held'
 *   store_queue_dispatch bool   true = also schedule chunk immediately
 *   store_transient_key string  transient key template (supports {id})
 *   store_transient_ttl int     seconds (default 3600)
 */
final class StorePayloadHandler extends AbstractHandler {

	public function handle( int $endpoint_id, array $data, WP_REST_Request $request ): mixed {
		$config       = $this->get_config( $endpoint_id );
		$destinations = (array) ( $config['store_destinations'] ?? [ 'log' ] );
		$results      = [];

		foreach ( $destinations as $dest ) {
			$results[ $dest ] = match ( $dest ) {
				'cpt'   => $this->store_cpt( $endpoint_id, $data, $config ),
				'queue' => $this->store_queue( $endpoint_id, $data, $config, $request ),
				'temp'  => $this->store_transient( $endpoint_id, $data, $config ),
				default => [ 'status' => 'logged' ],
			};
		}

		$this->output = sprintf(
			'Stored to: %s',
			implode( ', ', array_keys( $results ) )
		);

		return [
			'status'   => 'stored',
			'stored_to' => array_keys( $results ),
			'results'   => $results,
		];
	}

	/** @return array<string,mixed> */
	private function store_cpt( int $endpoint_id, array $data, array $config ): array {
		$post_type  = sanitize_key( $config['store_cpt_type'] ?? 'wprm_payload' );
		$title_key  = $config['store_cpt_title_key'] ?? '';
		$title      = $title_key && isset( $data[ $title_key ] )
			? sanitize_text_field( (string) $data[ $title_key ] )
			: sprintf( 'Payload %s — %s', $endpoint_id, current_time( 'Y-m-d H:i:s' ) );

		$post_id = wp_insert_post( [
			'post_type'    => $post_type,
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_content' => wp_json_encode( $data, JSON_PRETTY_PRINT ),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return [ 'error' => $post_id->get_error_message() ];
		}

		// Map payload keys to post meta.
		$field_map = (array) ( $config['store_cpt_map'] ?? [] );
		if ( empty( $field_map ) ) {
			// Auto-map everything.
			foreach ( $data as $k => $v ) {
				update_post_meta( $post_id, 'wprm_' . sanitize_key( $k ), is_array( $v ) ? wp_json_encode( $v ) : $v );
			}
		} else {
			foreach ( $field_map as $meta_key => $payload_key ) {
				if ( isset( $data[ $payload_key ] ) ) {
					$val = $data[ $payload_key ];
					update_post_meta( $post_id, sanitize_key( $meta_key ), is_array( $val ) ? wp_json_encode( $val ) : $val );
				}
			}
		}

		// Always store raw payload + endpoint ID.
		update_post_meta( $post_id, '_wprm_endpoint_id', $endpoint_id );
		update_post_meta( $post_id, '_wprm_payload_raw', wp_json_encode( $data ) );

		do_action( 'wprm_payload_stored_cpt', $post_id, $data, $endpoint_id );

		return [ 'post_id' => $post_id, 'post_type' => $post_type ];
	}

	/** @return array<string,mixed> */
	private function store_queue( int $endpoint_id, array $data, array $config, WP_REST_Request $request ): array {
		$queue        = new PayloadQueue();
		$queue_status = $config['store_queue_status'] ?? 'pending';
		$priority     = (int) ( $config['store_queue_priority'] ?? 5 );
		$max_attempts = (int) ( $config['store_queue_max_attempts'] ?? 3 );
		$slug         = (string) get_post_meta( $endpoint_id, 'wprm_route_slug', true );

		$queue_id = $queue->enqueue( $endpoint_id, $slug, $data, $queue_status, $priority, $max_attempts );

		// If pending + dispatch_now, schedule a chunk run immediately.
		if ( $queue_status === 'pending' && ! empty( $config['store_queue_dispatch'] ) ) {
			$queue->schedule_processing( $queue_id );
		}

		do_action( 'wprm_payload_queued', $queue_id, $data, $endpoint_id );

		return [ 'queue_id' => $queue_id, 'status' => $queue_status ];
	}

	/** @return array<string,mixed> */
	private function store_transient( int $endpoint_id, array $data, array $config ): array {
		$key_template = $config['store_transient_key'] ?? 'wprm_payload_{endpoint}_{time}';
		$ttl          = (int) ( $config['store_transient_ttl'] ?? HOUR_IN_SECONDS );

		$key = str_replace(
			[ '{endpoint}', '{time}' ],
			[ $endpoint_id, time() ],
			$key_template
		);
		$key = substr( sanitize_key( $key ), 0, 172 ); // WP transient key limit.

		set_transient( $key, $data, $ttl );

		do_action( 'wprm_payload_stored_transient', $key, $data, $endpoint_id );

		return [ 'transient_key' => $key, 'expires_in' => $ttl ];
	}

	/** @return array<string,mixed> */
	private function get_config( int $endpoint_id ): array {
		$raw = get_post_meta( $endpoint_id, 'wprm_action_config_store', true );
		if ( $raw && is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Fall back to individual meta keys (set by SCF fields).
		return [
			'store_destinations'    => array_filter( (array) get_post_meta( $endpoint_id, 'wprm_store_destinations', true ) ),
			'store_cpt_type'        => get_post_meta( $endpoint_id, 'wprm_store_cpt_type', true ),
			'store_cpt_title_key'   => get_post_meta( $endpoint_id, 'wprm_store_cpt_title_key', true ),
			'store_queue_status'    => get_post_meta( $endpoint_id, 'wprm_store_queue_status', true ) ?: 'pending',
			'store_queue_dispatch'  => (bool) get_post_meta( $endpoint_id, 'wprm_store_queue_dispatch', true ),
			'store_transient_key'   => get_post_meta( $endpoint_id, 'wprm_store_transient_key', true ),
			'store_transient_ttl'   => (int) get_post_meta( $endpoint_id, 'wprm_store_transient_ttl', true ) ?: HOUR_IN_SECONDS,
		];
	}
}
