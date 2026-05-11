<?php
/**
 * OAuth functions
 *
 * @package httpbridge
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

use HTTP_BRIDGE\Credential;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

add_action( 'rest_api_init', 'http_bridge_oauth_rest_api_init' );

/**
 * Registers oauth rest api routes.
 */
function http_bridge_oauth_rest_api_init() {
	register_rest_route(
		'http-bridge/v1',
		'/oauth/grant',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'http_bridge_oauth_grant',
			'permission_callback' => array( '\WPCT_PLUGIN\REST_Settings_Controller', 'permission_callback' ),
			'args'                => array( 'credential' => Credential::schema() ),
		)
	);

	register_rest_route(
		'http-bridge/v1',
		'/oauth/revoke',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'http_bridge_oauth_revoke',
			'permission_callback' => array( '\WPCT_PLUGIN\REST_Settings_Controller', 'permission_callback' ),
			'args'                => array( 'credential' => Credential::schema() ),
		)
	);

	register_rest_route(
		'http-bridge/v1',
		'/oauth/redirect',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'http_bridge_oauth_redirect',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * OAuth grant request callback.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return array|WP_Error
 */
function http_bridge_oauth_grant( $request ) {
	$data       = $request['credential'];
	$credential = new Credential( $data );
	$result     = $credential->oauth_grant_transient();

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	if ( ! $result['success'] ) {
		return new WP_Error( 'rest_bad_request', '', array( 'status' => 400 ) );
	}

	return $result;
}

/**
 * OAuth token revoke request callback.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return array|WP_Error
 */
function http_bridge_oauth_revoke( $request ) {
	$data       = $request['credential'];
	$credential = new Credential( $data );
	$result     = $credential->oauth_revoke();

	if ( ! $result ) {
		return new WP_Error( 'rest_bad_request', '', array( 'status' => 400 ) );
	}

	return array( 'success' => true );
}

/**
 * OAuth redirection request callback.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return array|null
 */
function http_bridge_oauth_redirect( $request ) {
	$credential = Credential::get_transient();
	if ( ! $credential ) {
		wp_die( esc_html( __( 'OAuth redirect timeout error', 'http-bridge' ) ) );
		return;
	}

	$result = $credential->oauth_redirect_callback( $request );
	if ( ! $result ) {
		wp_die( esc_html( __( 'Invalid OAuth redirect request', 'http-bridge' ) ) );
		return;
	}

	$url = site_url() . '/wp-admin/options-general.php?page=http-bridge&tab=http';

	if ( wp_safe_redirect( $url ) ) {
		exit( 302 );
	}

	return array( 'success' => false );
}
