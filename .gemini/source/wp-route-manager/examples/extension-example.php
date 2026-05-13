<?php
/**
 * Plugin Name:  WPRM Extension Example
 * Plugin URI:   https://github.com/pathway/wp-route-manager
 * Description:  Fully-annotated example showing every WP Route Manager extension point.
 *               Copy and modify this file to build your own integration.
 *               Or generate a fresh stub: wp wprm scaffold extension my-plugin
 * Version:      1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Pathway Group
 * License:           GPL-2.0-or-later
 * Text Domain:       wprm-extension-example
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
// HOOK 1: wprm_loaded
//
// Fires after WP Route Manager has finished bootstrapping all its subsystems:
// CPTs, SCF fields, REST routes, admin menus, Abilities API, and WP-CLI commands.
//
// Use this hook to safely reference WPRM classes and register integrations.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wprm_loaded', function ( WP_Route_Manager\Plugin $plugin ): void {

	// WPRM is fully loaded here. Safe to use any WPRM class.
	error_log( '[WPRM Example] Plugin loaded. Version ' . WPRM_VERSION );

} );


// ─────────────────────────────────────────────────────────────────────────────
// HOOK 2: wprm_register_abilities
//
// Fires inside AbilitiesRegistrar::register_abilities() after WPRM's own abilities
// are registered. Add your own abilities to the "wprm" category here.
//
// Abilities are exposed via GET /wp-json/wp-abilities/v1/abilities?namespace=wprm
// Requires WordPress 6.9+.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wprm_register_abilities', function (): void {

	if ( ! function_exists( 'wp_register_ability' ) ) {
		return; // WP < 6.9.
	}

	wp_register_ability( 'wprm/example/integration-active', [
		'label'       => 'WPRM Example Integration Active',
		'description' => 'Whether the WPRM Example plugin is active and configured.',
		'category'    => 'wprm',
		'value'       => true,
		'meta'        => [
			'readonly'     => true,
			'show_in_rest' => true,
		],
	] );

} );


// ─────────────────────────────────────────────────────────────────────────────
// HOOK 3: wprm_parsed_data  (filter)
//
// Fires after the request body is parsed (auto/json/form/raw/php) and before
// it is passed to the action dispatcher.
//
// Use this to:
//  - Add server-side fields to every payload (timestamp, site URL, etc.)
//  - Sanitize or normalize incoming data globally
//  - Reject requests by returning a WP_Error
//
// @param array           $data        Parsed body (key => value).
// @param WP_REST_Request $request     The full REST request object.
// @param int             $endpoint_id Endpoint post ID.
// @return array                       Modified data array.
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'wprm_parsed_data', function ( array $data, WP_REST_Request $request, int $endpoint_id ): array {

	// Inject server-side metadata so it's available to all handlers.
	$data['_meta'] = [
		'received_at' => current_time( 'c' ),
		'site_url'    => get_site_url(),
		'endpoint_id' => $endpoint_id,
		'user_ip'     => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
	];

	return $data;

}, 10, 3 );


// ─────────────────────────────────────────────────────────────────────────────
// HOOK 4: wprm_action_handler  (filter)
//
// Fires in Dispatcher::dispatch() and lets you replace or wrap any handler.
//
// The $handler object implements WP_Route_Manager\Actions\HandlerInterface:
//   handle( int $endpoint_id, array $data, WP_REST_Request $request ): mixed
//   get_output(): string
//
// Returning a different handler object changes what runs for this request.
//
// @param object $handler     Current handler instance.
// @param string $action_type wp_action | wp_filter | http_forward | php_snippet
// @param int    $endpoint_id Endpoint post ID.
// @return object             Handler to use (same or replacement).
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'wprm_action_handler', function ( object $handler, string $action_type, int $endpoint_id ): object {

	// Example: wrap all http_forward handlers with rate-limiting.
	if ( $action_type === 'http_forward' ) {
		return new class( $handler ) extends WP_Route_Manager\Actions\AbstractHandler {

			private object $inner;

			public function __construct( object $inner ) {
				$this->inner = $inner;
			}

			public function handle( int $ep, array $data, WP_REST_Request $req ): mixed {
				// Simple in-memory rate limit: 60 forwards per hour per IP.
				$ip      = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
				$key     = 'wprm_fwd_rl_' . md5( $ip . $ep );
				$current = (int) get_transient( $key );

				if ( $current >= 60 ) {
					return new WP_Error(
						'wprm_rate_limit',
						'Too many requests. Try again later.',
						[ 'status' => 429 ]
					);
				}

				set_transient( $key, $current + 1, HOUR_IN_SECONDS );

				$result        = $this->inner->handle( $ep, $data, $req );
				$this->output  = $this->inner->get_output();
				return $result;
			}
		};
	}

	// Return the original handler unmodified for all other types.
	return $handler;

}, 10, 3 );


// ─────────────────────────────────────────────────────────────────────────────
// HOOK 5: wprm_before_request  (action)
//
// Fires inside RequestHandler::handle(), after authentication has passed but
// before body parsing and dispatch.
//
// Use this to: log incoming requests to an external service, reject based on
// custom logic (via wp_die or throwing an exception), set request-scoped state.
//
// @param WP_REST_Request $request     The authenticated request.
// @param int             $endpoint_id Endpoint post ID.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wprm_before_request', function ( WP_REST_Request $request, int $endpoint_id ): void {

	// Example: write to a custom audit log table.
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'my_audit_log',
		[
			'endpoint_id' => $endpoint_id,
			'ip'          => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'method'      => $request->get_method(),
			'created_at'  => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%s', '%s' ]
	);

}, 10, 2 );


// ─────────────────────────────────────────────────────────────────────────────
// HOOK 6: wprm_after_request  (action)
//
// Fires inside RequestHandler::handle() after dispatch and logging, just before
// the response is returned to the caller.
//
// @param WP_REST_Response|WP_Error $response    The final response object.
// @param WP_REST_Request           $request     The original request.
// @param int                       $endpoint_id Endpoint post ID.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wprm_after_request', function ( WP_REST_Response|WP_Error $response, WP_REST_Request $request, int $endpoint_id ): void {

	$code = is_wp_error( $response ) ? 500 : $response->get_status();

	// Example: push success/failure metrics to a StatsD-style counter.
	if ( function_exists( 'my_metrics_increment' ) ) {
		my_metrics_increment( 'wprm.requests', 1, [
			'endpoint' => $endpoint_id,
			'status'   => $code >= 400 ? 'error' : 'success',
		] );
	}

}, 10, 3 );


// ─────────────────────────────────────────────────────────────────────────────
// HOOK 7: wprm_snippet_executed  (action)
//
// Fires after a PHP snippet runs, regardless of whether it succeeded.
// $output contains: print/echo capture, PHP errors, and return value type.
//
// @param int    $snippet_id  Snippet post ID.
// @param int    $endpoint_id Endpoint post ID.
// @param mixed  $result      Return value from snippet (or WP_Error on failure).
// @param string $output      Full debug output captured during execution.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wprm_snippet_executed', function ( int $snippet_id, int $endpoint_id, mixed $result, string $output ): void {

	if ( is_wp_error( $result ) ) {
		// Example: send a Slack alert on snippet failure.
		$message = sprintf(
			':x: WPRM Snippet #%d on endpoint #%d failed: %s',
			$snippet_id,
			$endpoint_id,
			$result->get_error_message()
		);

		if ( defined( 'MY_SLACK_WEBHOOK' ) ) {
			wp_remote_post( MY_SLACK_WEBHOOK, [
				'body'    => wp_json_encode( [ 'text' => $message ] ),
				'headers' => [ 'Content-Type' => 'application/json' ],
				'timeout' => 5,
				'blocking' => false, // fire-and-forget
			] );
		}
	}

}, 10, 4 );


// ─────────────────────────────────────────────────────────────────────────────
// HOOK 8: wprm_forward_data  (filter)
//
// Fires inside HttpForwardHandler::handle() just before the HTTP request is sent
// to the external URL. Runs AFTER the optional transform snippet (if configured).
//
// Use this to add signatures, merge additional fields, or suppress sensitive data.
//
// @param array           $data        Data about to be forwarded.
// @param int             $endpoint_id Endpoint post ID.
// @param WP_REST_Request $request     Original request.
// @return array                       Modified data to forward.
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'wprm_forward_data', function ( array $data, int $endpoint_id, WP_REST_Request $request ): array {

	// Example: add an HMAC signature so the receiving end can verify authenticity.
	$secret    = defined( 'MY_WEBHOOK_SECRET' ) ? MY_WEBHOOK_SECRET : 'changeme';
	$payload   = wp_json_encode( $data );
	$signature = hash_hmac( 'sha256', $payload, $secret );

	// The receiving end can verify with:
	// hash_equals( hash_hmac( 'sha256', $payload, $secret ), $incoming_signature )
	$data['_signature'] = $signature;
	$data['_sent_at']   = current_time( 'c' );

	// Strip any internal meta added by wprm_parsed_data.
	unset( $data['_meta'] );

	return $data;

}, 10, 3 );


// ─────────────────────────────────────────────────────────────────────────────
// HOOK 9: Custom WP action handler
//
// When an endpoint uses action type "wp_action" with hook "my_crm_ingest",
// this add_action() fires automatically with the parsed request body.
//
// You can have multiple add_action() listeners on the same hook — they all fire.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'my_crm_ingest', function ( array $data, WP_REST_Request $request, int $endpoint_id ): void {

	// Validate.
	$email = sanitize_email( $data['email'] ?? '' );
	if ( ! is_email( $email ) ) {
		error_log( '[WPRM Example] my_crm_ingest: invalid or missing email.' );
		return;
	}

	// Example: create/update a WP user.
	$user = get_user_by( 'email', $email );

	if ( $user ) {
		wp_update_user( [
			'ID'         => $user->ID,
			'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
		] );
	} else {
		$user_id = wp_insert_user( [
			'user_email' => $email,
			'user_login' => $email,
			'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
			'role'        => 'subscriber',
		] );

		if ( ! is_wp_error( $user_id ) ) {
			update_user_meta( $user_id, 'wprm_ingest_source', sanitize_text_field( $data['source'] ?? 'api' ) );
		}
	}

}, 10, 3 );


// ─────────────────────────────────────────────────────────────────────────────
// HOOK 10: Custom WP filter handler
//
// When an endpoint uses action type "wp_filter" with hook "my_crm_transform",
// the return value of apply_filters() is sent back as the JSON response.
// You MUST return something (array or WP_Error).
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'my_crm_transform', function ( mixed $data, WP_REST_Request $request, int $endpoint_id ): array|WP_Error {

	$data = is_array( $data ) ? $data : [];

	// Validate.
	if ( empty( $data['order_id'] ) ) {
		return new WP_Error( 'missing_order_id', 'order_id is required.', [ 'status' => 422 ] );
	}

	$order_id = absint( $data['order_id'] );
	$order    = wc_get_order( $order_id ); // works if WooCommerce is active.

	if ( ! $order ) {
		return new WP_Error( 'order_not_found', 'Order not found.', [ 'status' => 404 ] );
	}

	return [
		'status'       => 'ok',
		'order_id'     => $order_id,
		'order_status' => $order->get_status(),
		'order_total'  => $order->get_total(),
		'currency'     => $order->get_currency(),
		'customer'     => [
			'email' => $order->get_billing_email(),
			'name'  => $order->get_formatted_billing_full_name(),
		],
	];

}, 10, 3 );
