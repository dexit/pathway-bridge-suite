<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Actions;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class HttpForwardHandler extends AbstractHandler {

	public function handle( int $endpoint_id, array $data, WP_REST_Request $request ): mixed {
		$url     = (string) get_post_meta( $endpoint_id, 'wprm_forward_url', true );
		$method  = strtoupper( (string) get_post_meta( $endpoint_id, 'wprm_forward_method', true ) ) ?: 'POST';
		$timeout = (int) get_post_meta( $endpoint_id, 'wprm_forward_timeout', true ) ?: 10;

		if ( empty( $url ) ) {
			return new \WP_Error( 'wprm_no_forward_url', __( 'No forward URL configured.', 'wp-route-manager' ), [ 'status' => 500 ] );
		}

		// Optional transform snippet.
		$transform_id = (int) get_post_meta( $endpoint_id, 'wprm_transform_snippet_id', true );
		if ( $transform_id && WPRM_ALLOW_PHP_SNIPPETS ) {
			$data = $this->transform( $data, $transform_id, $request, $endpoint_id );
		}

		// Allow filter-based transform even without PHP snippets.
		$data = apply_filters( 'wprm_forward_data', $data, $endpoint_id, $request );

		// Build headers.
		$headers = [ 'Content-Type' => 'application/json' ];
		$raw_headers = get_post_meta( $endpoint_id, 'wprm_forward_headers', true );

		if ( is_array( $raw_headers ) ) {
			foreach ( $raw_headers as $row ) {
				$k = sanitize_text_field( $row['header_key'] ?? '' );
				$v = sanitize_text_field( $row['header_value'] ?? '' );
				if ( $k ) {
					$headers[ $k ] = $v;
				}
			}
		}

		$args = [
			'method'  => $method,
			'headers' => $headers,
			'body'    => wp_json_encode( $data ),
			'timeout' => $timeout,
		];

		if ( $method === 'GET' ) {
			$url  = add_query_arg( $data, $url );
			$args['body'] = null;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->output = 'HTTP forward error: ' . $response->get_error_message();
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		$this->output = sprintf( 'Forwarded to %s — %d response (%d bytes)', $url, $code, strlen( $body ) );

		// Try to decode JSON response from the upstream.
		$decoded = json_decode( $body, true );
		return $decoded ?? [ 'forwarded' => true, 'upstream_status' => $code, 'body' => $body ];
	}

	private function transform( array $data, int $snippet_id, WP_REST_Request $request, int $endpoint_id ): array {
		$code = get_post_meta( $snippet_id, 'wprm_snippet_code', true );
		if ( empty( $code ) ) {
			return $data;
		}
		try {
			$result = ( static function ( string $__code, array $data, WP_REST_Request $request, int $endpoint ): mixed {
				return eval( '?>' . $__code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
			} )( $code, $data, $request, $endpoint_id );

			return is_array( $result ) ? $result : $data;
		} catch ( \Throwable $e ) {
			return $data;
		}
	}
}
