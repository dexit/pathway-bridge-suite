<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Cli;

use WP_Route_Manager\PostTypes\EndpointPostType;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Fire a real HTTP test request against a registered WP Route Manager endpoint.
 *
 * Uses wp_remote_request() so the request travels through the full WP REST stack,
 * including authentication, body parsing, and action dispatch — exactly as an
 * external caller would experience it.
 *
 * ## EXAMPLES
 *
 *   wp wprm test demo/ping --method=GET
 *   wp wprm test demo/action --method=POST --body='{"order_id":123}' --key=wrmk_abc123
 *   wp wprm test 5 --method=POST --body='{"foo":"bar"}' --key=wrmk_abc123
 *
 * @package WP_Route_Manager
 * @when after_wp_load
 */
class TestCommand {

	/**
	 * Send a test HTTP request to an endpoint and display the full response.
	 *
	 * ## OPTIONS
	 *
	 * <slug-or-id>
	 * : Endpoint route slug (e.g. "demo/ping") or post ID.
	 *
	 * [--method=<method>]
	 * : HTTP method: GET, POST, PUT, PATCH, DELETE. Default: POST.
	 *
	 * [--body=<json>]
	 * : JSON string to send as the request body.
	 *
	 * [--key=<api_key>]
	 * : Plain API key to send in the X-WRM-Key header.
	 *
	 * [--params=<json>]
	 * : JSON object of query params to append to the URL.
	 *
	 * [--timeout=<seconds>]
	 * : Request timeout in seconds. Default: 15.
	 *
	 * [--show-headers]
	 * : Also print the response headers.
	 *
	 * ## EXAMPLES
	 *
	 *   # Public GET endpoint — no key needed
	 *   wp wprm test demo/ping --method=GET
	 *
	 *   # Authenticated POST with JSON body
	 *   wp wprm test demo/action --method=POST \
	 *     --body='{"name":"Jane","email":"jane@example.com"}' \
	 *     --key=wrmk_abc123def456
	 *
	 *   # Reference by post ID
	 *   wp wprm test 7 --method=POST --body='{"test":true}' --key=wrmk_abc123
	 *
	 *   # GET with query params
	 *   wp wprm test demo/ping --method=GET --params='{"foo":"bar"}'
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc ): void {
		$slug_or_id = $args[0] ?? '';
		if ( ! $slug_or_id ) {
			WP_CLI::error( 'Provide a route slug or endpoint post ID.' );
		}

		// Resolve to post ID.
		$endpoint_id = $this->resolve_endpoint( $slug_or_id );
		if ( ! $endpoint_id ) {
			WP_CLI::error( "No endpoint found for '{$slug_or_id}'. Use 'wp wprm endpoint list' to see available routes." );
		}

		$ns      = get_option( 'wprm_namespace', 'wprm' );
		$ver     = get_option( 'wprm_api_version', 'v1' );
		$slug    = (string) get_post_meta( $endpoint_id, 'wprm_route_slug', true );
		$method  = strtoupper( $assoc['method'] ?? 'POST' );
		$key     = $assoc['key'] ?? '';
		$timeout = absint( $assoc['timeout'] ?? 15 );
		$show_headers = isset( $assoc['show-headers'] );

		$url = rest_url( $ns . '/' . $ver . '/' . ltrim( $slug, '/' ) );

		// Append query params.
		if ( ! empty( $assoc['params'] ) ) {
			$params = json_decode( $assoc['params'], true );
			if ( is_array( $params ) ) {
				$url = add_query_arg( $params, $url );
			}
		}

		// Build headers.
		$headers = [ 'Content-Type' => 'application/json' ];
		if ( $key ) {
			$headers['X-WRM-Key'] = $key;
		}

		// Body.
		$body = null;
		if ( ! empty( $assoc['body'] ) ) {
			$body = $assoc['body'];
			// Validate it's real JSON.
			if ( json_decode( $body ) === null && json_last_error() !== JSON_ERROR_NONE ) {
				WP_CLI::warning( 'Body does not appear to be valid JSON — sending as-is.' );
			}
		}

		// Print request summary.
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%B┌─ Request ────────────────────────────────────────────%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%B│%n' ) . ' ' . WP_CLI::colorize( '%Y' . $method . '%n' ) . ' ' . $url );
		if ( $key ) {
			WP_CLI::line( WP_CLI::colorize( '%B│%n' ) . ' X-WRM-Key: ' . substr( $key, 0, 12 ) . '…' );
		}
		if ( $body ) {
			WP_CLI::line( WP_CLI::colorize( '%B│%n' ) . ' Body: ' . ( strlen( $body ) > 200 ? substr( $body, 0, 200 ) . '…' : $body ) );
		}
		WP_CLI::line( WP_CLI::colorize( '%B└──────────────────────────────────────────────────────%n' ) );
		WP_CLI::line( '' );

		// Fire request.
		$start = microtime( true );
		$response = wp_remote_request( $url, [
			'method'  => $method,
			'headers' => $headers,
			'body'    => $body,
			'timeout' => $timeout,
		] );
		$elapsed_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( 'HTTP request failed: ' . $response->get_error_message() );
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$body_out = wp_remote_retrieve_body( $response );

		$code_str = $code >= 400
			? WP_CLI::colorize( '%R' . $code . '%n' )
			: WP_CLI::colorize( '%G' . $code . '%n' );

		WP_CLI::line( WP_CLI::colorize( '%B┌─ Response ───────────────────────────────────────────%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%B│%n' ) . ' HTTP ' . $code_str . ' (' . $elapsed_ms . 'ms)' );

		if ( $show_headers ) {
			WP_CLI::line( WP_CLI::colorize( '%B│%n' ) );
			WP_CLI::line( WP_CLI::colorize( '%B│%n' ) . ' Headers:' );
			foreach ( wp_remote_retrieve_headers( $response ) as $k => $v ) {
				WP_CLI::line( WP_CLI::colorize( '%B│%n' ) . '   ' . $k . ': ' . $v );
			}
		}

		WP_CLI::line( WP_CLI::colorize( '%B│%n' ) );

		// Pretty-print JSON body.
		$decoded = json_decode( $body_out, true );
		$body_display = ( $decoded !== null )
			? (string) wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			: $body_out;

		foreach ( explode( "\n", $body_display ) as $line ) {
			WP_CLI::line( WP_CLI::colorize( '%B│%n' ) . ' ' . $line );
		}

		WP_CLI::line( WP_CLI::colorize( '%B└──────────────────────────────────────────────────────%n' ) );
		WP_CLI::line( '' );

		if ( $code >= 400 ) {
			WP_CLI::warning( sprintf( 'Request completed with HTTP %d.', $code ) );
		} else {
			WP_CLI::success( sprintf( 'HTTP %d in %dms.', $code, $elapsed_ms ) );
		}
	}

	private function resolve_endpoint( string $slug_or_id ): int {
		if ( is_numeric( $slug_or_id ) ) {
			$post = get_post( (int) $slug_or_id );
			if ( $post && $post->post_type === EndpointPostType::POST_TYPE ) {
				return $post->ID;
			}
		}

		$posts = get_posts( [
			'post_type'      => EndpointPostType::POST_TYPE,
			'post_status'    => 'any',
			'meta_key'       => 'wprm_route_slug',
			'meta_value'     => $slug_or_id,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		] );

		return $posts ? (int) $posts[0] : 0;
	}
}
