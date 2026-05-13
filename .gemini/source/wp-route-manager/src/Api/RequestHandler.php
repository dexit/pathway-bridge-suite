<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Route_Manager\Actions\Dispatcher;
use WP_Route_Manager\DB\LogRepository;

defined( 'ABSPATH' ) || exit;

final class RequestHandler {

	private int $endpoint_id;
	private Authenticator $auth;
	private BodyParser $body_parser;
	private Dispatcher $dispatcher;
	private LogRepository $logs;

	public function __construct( int $endpoint_id ) {
		$this->endpoint_id = $endpoint_id;
		$this->auth        = new Authenticator();
		$this->body_parser = new BodyParser();
		$this->dispatcher  = new Dispatcher();
		$this->logs        = new LogRepository();
	}

	public function permission( WP_REST_Request $request ): bool|WP_Error {
		$require_auth = (bool) get_post_meta( $this->endpoint_id, 'wprm_require_auth', true );

		if ( ! $require_auth ) {
			return true;
		}

		$key_row = $this->auth->validate( $request, $this->endpoint_id );

		if ( ! $key_row ) {
			return new WP_Error(
				'wprm_unauthorized',
				__( 'Invalid or missing API key.', 'wp-route-manager' ),
				[ 'status' => 401 ]
			);
		}

		// Stash key row so handle() can access it.
		$request->set_param( '_wprm_key_row', $key_row );

		return true;
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$start_time = microtime( true );

		// Allow extension before processing.
		do_action( 'wprm_before_request', $request, $this->endpoint_id );

		// Parse body.
		$parse_mode = (string) get_post_meta( $this->endpoint_id, 'wprm_body_parse_mode', true ) ?: 'auto';
		$parse_snippet_id = (int) get_post_meta( $this->endpoint_id, 'wprm_body_parse_snippet_id', true );

		$parsed = $this->body_parser->parse( $request, $parse_mode, $parse_snippet_id, $this->endpoint_id );

		// Allow data transform via filter.
		$data = apply_filters( 'wprm_parsed_data', $parsed['parsed'], $request, $this->endpoint_id );

		// Dispatch.
		$result        = $this->dispatcher->dispatch( $this->endpoint_id, $data, $request );
		$snippet_output = $this->dispatcher->get_last_output();

		$duration_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		// Build response.
		$response = $this->build_response( $result );

		// Log.
		$log_enabled = (bool) get_option( 'wprm_global_logging', true );
		$log_endpoint = (bool) get_post_meta( $this->endpoint_id, 'wprm_log_requests', true );

		if ( $log_enabled && $log_endpoint ) {
			$key_row = $request->get_param( '_wprm_key_row' );
			$this->logs->record( [
				'endpoint_id'         => $this->endpoint_id,
				'endpoint_slug'       => (string) get_post_meta( $this->endpoint_id, 'wprm_route_slug', true ),
				'key_id'              => $key_row ? (int) $key_row->id : null,
				'method'              => $request->get_method(),
				'caller_ip'           => $this->get_ip(),
				'request_headers'     => $this->safe_headers( $request ),
				'request_params'      => (array) $request->get_query_params(),
				'request_body_raw'    => $parsed['raw'],
				'request_body_parsed' => $data,
				'response_code'       => is_wp_error( $result )
					? (int) ( $result->get_error_data()['status'] ?? 500 )
					: ( (int) get_post_meta( $this->endpoint_id, 'wprm_response_code', true ) ?: 200 ),
				'response_body'       => is_wp_error( $result )
					? $result->get_error_message()
					: wp_json_encode( $result ),
				'duration_ms'         => $duration_ms,
				'snippet_output'      => $snippet_output,
				'error'               => is_wp_error( $result ) ? $result->get_error_message() : null,
			] );
		}

		do_action( 'wprm_after_request', $response, $request, $this->endpoint_id );

		return $response;
	}

	private function build_response( mixed $result ): WP_REST_Response|WP_Error {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response_type = (string) get_post_meta( $this->endpoint_id, 'wprm_response_type', true ) ?: 'json';
		$code          = (int) get_post_meta( $this->endpoint_id, 'wprm_response_code', true ) ?: 200;

		switch ( $response_type ) {
			case 'text':
				$text = is_string( $result ) ? $result : wp_json_encode( $result );
				$resp = new WP_REST_Response( $text, $code );
				$resp->header( 'Content-Type', 'text/plain; charset=utf-8' );
				return $resp;

			case 'redirect':
				$url = (string) get_post_meta( $this->endpoint_id, 'wprm_redirect_url', true );
				$resp = new WP_REST_Response( null, 302 );
				$resp->header( 'Location', $url );
				return $resp;

			case 'none':
				return new WP_REST_Response( null, $code );

			default: // json
				return rest_ensure_response( $result ?? [ 'status' => 'ok' ] );
		}
	}

	/** @return array<string,string> */
	private function safe_headers( WP_REST_Request $request ): array {
		$headers = (array) $request->get_headers();
		// Remove sensitive headers from log.
		unset( $headers['x_wrm_key'], $headers['authorization'] );
		return array_map( fn( $v ) => is_array( $v ) ? implode( ', ', $v ) : $v, $headers );
	}

	private function get_ip(): string {
		$keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ];
		foreach ( $keys as $key ) {
			$ip = sanitize_text_field( $_SERVER[ $key ] ?? '' );
			if ( $ip ) {
				return explode( ',', $ip )[0];
			}
		}
		return '';
	}
}
