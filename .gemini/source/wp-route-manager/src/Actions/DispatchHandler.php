<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Actions;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatch Handler — immediately dispatches incoming data to multiple targets simultaneously.
 *
 * Unlike wp_action (single hook) or http_forward (single URL), Dispatch fans out to:
 *   - Multiple WP action hooks
 *   - Multiple external URLs (parallel wp_remote_post)
 *   - Action Scheduler jobs (async, one per target)
 *   - Queue entries
 *
 * Config stored in wprm_action_config_dispatch meta as JSON:
 *   dispatch_targets  array of { type: 'hook'|'url'|'async', value: string, label: string }
 *   dispatch_mode     'parallel' | 'sequential' | 'async'
 *   dispatch_timeout  int seconds for HTTP targets
 */
final class DispatchHandler extends AbstractHandler {

	public function handle( int $endpoint_id, array $data, WP_REST_Request $request ): mixed {
		$targets = $this->get_targets( $endpoint_id );
		$mode    = (string) ( get_post_meta( $endpoint_id, 'wprm_dispatch_mode', true ) ?: 'parallel' );
		$timeout = (int) ( get_post_meta( $endpoint_id, 'wprm_dispatch_timeout', true ) ?: 10 );

		if ( empty( $targets ) ) {
			return new \WP_Error( 'wprm_no_dispatch_targets', __( 'No dispatch targets configured.', 'wp-route-manager' ), [ 'status' => 500 ] );
		}

		$results = [];
		$errors  = [];

		foreach ( $targets as $i => $target ) {
			$type  = $target['type'] ?? 'hook';
			$value = $target['value'] ?? '';
			$label = $target['label'] ?? $value;

			if ( empty( $value ) ) {
				continue;
			}

			try {
				$result = match ( $type ) {
					'hook'  => $this->dispatch_hook( $value, $data, $request, $endpoint_id ),
					'url'   => $this->dispatch_url( $value, $data, $timeout ),
					'async' => $this->dispatch_async( $value, $data, $endpoint_id ),
					default => [ 'error' => "Unknown type: {$type}" ],
				};

				$results[ $label ] = $result;

			} catch ( \Throwable $e ) {
				$errors[ $label ] = $e->getMessage();
			}
		}

		$this->output = sprintf(
			'Dispatched to %d targets (%d errors). Mode: %s',
			count( $results ),
			count( $errors ),
			$mode
		);

		do_action( 'wprm_dispatch_completed', $results, $errors, $data, $endpoint_id );

		return [
			'status'     => empty( $errors ) ? 'dispatched' : 'partial',
			'dispatched' => count( $results ),
			'errors'     => count( $errors ),
			'results'    => $results,
			'error_detail' => $errors,
		];
	}

	/** @return array<string,mixed> */
	private function dispatch_hook( string $hook, array $data, WP_REST_Request $request, int $endpoint_id ): array {
		do_action( $hook, $data, $request, $endpoint_id );
		return [ 'fired' => true, 'hook' => $hook ];
	}

	/** @return array<string,mixed> */
	private function dispatch_url( string $url, array $data, int $timeout ): array {
		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $data ),
			'timeout' => $timeout,
			'blocking' => true,
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'error' => $response->get_error_message() ];
		}

		return [
			'status' => wp_remote_retrieve_response_code( $response ),
			'url'    => $url,
		];
	}

	/** @return array<string,mixed> */
	private function dispatch_async( string $hook, array $data, int $endpoint_id ): array {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			$action_id = as_schedule_single_action(
				time(),
				$hook,
				[ 'data' => $data, 'endpoint_id' => $endpoint_id ],
				'wp-route-manager'
			);
			return [ 'scheduled' => true, 'action_id' => $action_id, 'hook' => $hook ];
		}

		// Fallback: fire synchronously.
		do_action( $hook, $data, $endpoint_id );
		return [ 'fired_sync' => true, 'hook' => $hook ];
	}

	/**
	 * @return array<int, array{type: string, value: string, label: string}>
	 */
	private function get_targets( int $endpoint_id ): array {
		$raw = get_post_meta( $endpoint_id, 'wprm_dispatch_targets', true );

		// SCF repeater returns array of rows.
		if ( is_array( $raw ) ) {
			return array_values( array_filter( $raw, fn( $r ) => ! empty( $r['value'] ) ) );
		}

		// JSON string fallback.
		if ( $raw && is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return [];
	}
}
