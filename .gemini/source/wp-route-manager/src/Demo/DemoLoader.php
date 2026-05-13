<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Demo;

use WP_Route_Manager\DB\ApiKeyRepository;
use WP_Route_Manager\PostTypes\EndpointPostType;
use WP_Route_Manager\PostTypes\SnippetPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Loads demo content on first plugin activation.
 *
 * Creates:
 *   1. Four PHP snippets (action handler, filter handler, body parser, HTTP transform)
 *   2. Five endpoints (one per action type + a demo public GET)
 *   3. One demo API key
 *
 * All demo posts are tagged with wprm_demo = 1 meta for easy cleanup.
 */
final class DemoLoader {

	public function load(): void {
		if ( get_option( 'wprm_demo_loaded' ) ) {
			return;
		}

		$snippets  = $this->create_snippets();
		$endpoints = $this->create_endpoints( $snippets );
		$this->create_api_key( $endpoints );
	}

	// ── Snippets ──────────────────────────────────────────────────────────────

	/** @return array<string,int> */
	private function create_snippets(): array {
		return [
			'action'    => $this->insert_snippet(
				'Demo: Log Incoming Data (Action Handler)',
				'action_handler',
				'// Available: $data (array), $request (WP_REST_Request), $endpoint (int)
// This snippet fires as a wp_action handler.
// $data contains the parsed request body.

// Log to PHP error log (visible in debug.log when WP_DEBUG_LOG is on)
error_log( \'[WPRM Demo] Received data: \' . print_r( $data, true ) );

// Store in a transient for 5 minutes so you can inspect it from wp-admin
set_transient( \'wprm_demo_last_payload\', $data, 5 * MINUTE_IN_SECONDS );

// Fire a custom WP action so other plugins can respond
do_action( \'wprm_demo_data_received\', $data );

// Action handlers do not need to return anything.
'
			),

			'filter'    => $this->insert_snippet(
				'Demo: Enrich and Return Data (Filter Handler)',
				'filter_handler',
				'// Available: $data (array), $request (WP_REST_Request), $endpoint (int)
// This snippet must return the response array (or WP_Error).

$response = [
    \'received\'  => $data,
    \'site\'       => get_bloginfo( \'name\' ),
    \'timestamp\'  => current_time( \'c\' ),
    \'wp_version\' => get_bloginfo( \'version\' ),
    \'ip\'         => $_SERVER[\'REMOTE_ADDR\'] ?? \'unknown\',
];

// You can also validate the incoming data:
if ( empty( $data[\'email\'] ) ) {
    // Return a WP_Error to send an HTTP 422 response
    // return new WP_Error( \'missing_email\', \'Email is required.\', [ \'status\' => 422 ] );
}

return $response;
'
			),

			'parser'    => $this->insert_snippet(
				'Demo: Custom Body Parser',
				'body_parser',
				'// Available: $raw (string — the raw request body)
// Must return an array.
// Use this to parse non-standard body formats (XML, CSV, custom protocols).

// Example: parse a simple key=value format separated by newlines
$lines  = explode( "\n", trim( $raw ) );
$parsed = [];

foreach ( $lines as $line ) {
    $parts = explode( \'=\', $line, 2 );
    if ( count( $parts ) === 2 ) {
        $parsed[ trim( $parts[0] ) ] = trim( $parts[1] );
    }
}

// Fallback to raw if nothing parsed
if ( empty( $parsed ) ) {
    $parsed = [ \'raw\' => $raw ];
}

return $parsed;
'
			),

			'transform' => $this->insert_snippet(
				'Demo: HTTP Forward Transform',
				'http_transform',
				'// Available: $data (array — parsed body), $request (WP_REST_Request), $endpoint (int)
// This snippet is run before forwarding data to an external URL.
// Must return the (possibly modified) $data array.

// Example: map WooCommerce webhook fields to a CRM format
return [
    \'contact_email\'  => $data[\'billing\'][\'email\'] ?? $data[\'email\'] ?? \'\',
    \'contact_name\'   => trim( ( $data[\'billing\'][\'first_name\'] ?? \'\' ) . \' \' . ( $data[\'billing\'][\'last_name\'] ?? \'\' ) ),
    \'order_total\'    => $data[\'total\'] ?? 0,
    \'order_id\'       => $data[\'id\'] ?? null,
    \'source\'         => \'woocommerce\',
    \'timestamp\'      => current_time( \'c\' ),
    \'_raw\'           => $data, // pass-through original
];
'
			),
		];
	}

	private function insert_snippet( string $title, string $type, string $code ): int {
		$id = wp_insert_post( [
			'post_title'   => $title,
			'post_type'    => SnippetPostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_content' => '',
		] );

		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		update_post_meta( $id, 'wprm_snippet_type',          $type );
		update_post_meta( $id, 'wprm_snippet_code',          $code );
		update_post_meta( $id, 'wprm_snippet_catch_errors',  1 );
		update_post_meta( $id, 'wprm_snippet_timeout',       10 );
		update_post_meta( $id, 'wprm_demo',                  1 );
		update_post_meta( $id, 'wprm_snippet_description',
			'Demo snippet — feel free to modify or delete. Created by WP Route Manager on first activation.'
		);

		return $id;
	}

	// ── Endpoints ─────────────────────────────────────────────────────────────

	/** @param array<string,int> $snippets */
	private function create_endpoints( array $snippets ): array {
		$ids = [];

		// 1. WP Action endpoint — fires do_action hook.
		$ids['action'] = $this->insert_endpoint( [
			'name'             => 'Demo: WP Action Hook',
			'route_slug'       => 'demo/action',
			'method'           => 'POST',
			'action_type'      => 'wp_action',
			'action_hook'      => 'wprm_demo_ingest',
			'require_auth'     => 1,
			'description'      => 'POSTing to this endpoint fires do_action("wprm_demo_ingest", $data). Add your own add_action("wprm_demo_ingest", ...) in functions.php to handle the data.',
		] );

		// 2. WP Filter endpoint — apply_filters + return JSON.
		$ids['filter'] = $this->insert_endpoint( [
			'name'             => 'Demo: WP Filter (returns JSON)',
			'route_slug'       => 'demo/filter',
			'method'           => 'POST',
			'action_type'      => 'wp_filter',
			'action_hook'      => 'wprm_demo_transform',
			'require_auth'     => 1,
			'description'      => 'POSTing fires apply_filters("wprm_demo_transform", $data) and returns the result. Hook in to modify the response.',
		] );

		// 3. PHP Snippet endpoint — runs the filter snippet directly.
		$ids['snippet'] = $this->insert_endpoint( [
			'name'                  => 'Demo: PHP Snippet',
			'route_slug'            => 'demo/snippet',
			'method'                => 'POST',
			'action_type'           => 'php_snippet',
			'action_snippet_id'     => $snippets['filter'],
			'require_auth'          => 1,
			'description'           => 'Runs the "Demo: Enrich and Return Data" snippet. Enable WPRM_ALLOW_PHP_SNIPPETS in wp-config.php first.',
		] );

		// 4. HTTP Forward endpoint — relays to httpbin (public test endpoint).
		$ids['forward'] = $this->insert_endpoint( [
			'name'             => 'Demo: HTTP Forward to httpbin',
			'route_slug'       => 'demo/forward',
			'method'           => 'POST',
			'action_type'      => 'http_forward',
			'forward_url'      => 'https://httpbin.org/post',
			'forward_method'   => 'POST',
			'forward_timeout'  => 10,
			'transform_snippet_id' => $snippets['transform'],
			'require_auth'     => 1,
			'description'      => 'Forwards the request body to httpbin.org/post (a public HTTP echo service). The transform snippet remaps fields before forwarding.',
		] );

		// 5. Public GET endpoint — no auth required.
		$ids['public'] = $this->insert_endpoint( [
			'name'           => 'Demo: Public GET (no auth)',
			'route_slug'     => 'demo/ping',
			'method'         => 'GET',
			'action_type'    => 'wp_filter',
			'action_hook'    => 'wprm_demo_ping',
			'require_auth'   => 0,
			'response_type'  => 'json',
			'description'    => 'Public endpoint, no API key needed. Returns site info. Try: GET /wp-json/wprm/v1/demo/ping',
		] );

		// Register a default filter for the ping endpoint.
		add_filter( 'wprm_demo_ping', function ( $data ) {
			return [
				'pong'       => true,
				'site'       => get_bloginfo( 'name' ),
				'version'    => WPRM_VERSION,
				'time'       => current_time( 'c' ),
				'php'        => PHP_VERSION,
				'wordpress'  => get_bloginfo( 'version' ),
				'snippets_enabled' => WPRM_ALLOW_PHP_SNIPPETS,
			];
		} );

		return $ids;
	}

	private function insert_endpoint( array $data ): int {
		$id = wp_insert_post( [
			'post_title'   => $data['name'],
			'post_type'    => EndpointPostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_content' => $data['description'] ?? '',
		] );

		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		$meta_map = [
			'wprm_route_slug'            => $data['route_slug'] ?? '',
			'wprm_method'                => $data['method'] ?? 'POST',
			'wprm_action_type'           => $data['action_type'] ?? 'wp_action',
			'wprm_action_hook'           => $data['action_hook'] ?? '',
			'wprm_forward_url'           => $data['forward_url'] ?? '',
			'wprm_forward_method'        => $data['forward_method'] ?? 'POST',
			'wprm_forward_timeout'       => $data['forward_timeout'] ?? 10,
			'wprm_action_snippet_id'     => $data['action_snippet_id'] ?? 0,
			'wprm_transform_snippet_id'  => $data['transform_snippet_id'] ?? 0,
			'wprm_require_auth'          => $data['require_auth'] ?? 1,
			'wprm_body_parse_mode'       => $data['body_parse_mode'] ?? 'auto',
			'wprm_response_type'         => $data['response_type'] ?? 'json',
			'wprm_response_code'         => $data['response_code'] ?? 200,
			'wprm_log_requests'          => 1,
			'wprm_demo'                  => 1,
		];

		foreach ( $meta_map as $key => $value ) {
			if ( $value !== '' && $value !== 0 ) {
				update_post_meta( $id, $key, $value );
			}
		}

		return $id;
	}

	// ── API Key ───────────────────────────────────────────────────────────────

	private function create_api_key( array $endpoint_ids ): void {
		$repo   = new ApiKeyRepository();
		$result = $repo->create( 'Demo Key (created on activation)', array_values( $endpoint_ids ) );

		// Store plain key temporarily in a transient (admin can view it once from dashboard).
		set_transient( 'wprm_demo_api_key', $result['plain_key'], DAY_IN_SECONDS );
		update_option( 'wprm_demo_key_id', $result['id'] );
	}
}
