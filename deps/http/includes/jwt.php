<?php
/**
 * JSON Web Token functions
 *
 * @package httpbridge
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

use HTTP_BRIDGE\JWT;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

add_action( 'determine_current_user', 'http_bridge_jwt_determine_current_user', 1, 20 );
add_filter( 'rest_pre_dispatch', 'http_bridge_jwt_rest_pre_dispatch' );
add_action( 'rest_api_init', 'http_bridge_jwt_rest_api_init' );

/**
 * Registers REST API jwt authentication routes.
 */
function http_bridge_jwt_rest_api_init() {
	register_rest_route(
		'http-bridge/v1',
		'/jwt/auth',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'http_bridge_jwt_auth',
			'permission_callback' => 'http_bridge_jwt_auth_permission_callback',
		),
	);

	register_rest_route(
		'http-bridge/v1',
		'/jwt/validate',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'http_bridge_jwt_validate',
			'permission_callback' => 'http_bridge_jwt_validate_permission_callback',
		),
	);
}

/**
 * Authorization header getter.
 *
 * @return string Bearer token.
 *
 * @throws Exception If not authorization or if it's invalid.
 */
function http_bridge_jwt_authorization() {
	if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		$auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
	} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
		$auth_header = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
	}

	if ( ! isset( $auth_header ) ) {
		throw new Exception( 'Authorization header not found', 400 );
	}

	[$token] = sscanf( $auth_header, 'Bearer %s' );
	if ( ! $token ) {
		throw new Exception( 'Authorization header malformed', 400 );
	}

	return $token;
}

/**
 * Auth REST request callback.
 *
 * @return array
 */
function http_bridge_jwt_auth() {
	global $http_bridge_jwt_user;

	$issued_at  = time();
	$not_before = $issued_at;

	$expire = apply_filters(
		'http_bridge_jwt_auth_expire',
		$issued_at + 60 * 60 * 24 * 7,
		$issued_at
	);

	$claims = array(
		'iss'  => get_bloginfo( 'url' ),
		'iat'  => $issued_at,
		'nbf'  => $not_before,
		'exp'  => $expire,
		'data' => array(
			'user_id' => $http_bridge_jwt_user->data->ID,
		),
	);

	$token = ( new JWT() )->encode( $claims );

	return array(
		'token'        => $token,
		'user_email'   => $http_bridge_jwt_user->data->user_email,
		'user_login'   => $http_bridge_jwt_user->data->user_login,
		'display_name' => $http_bridge_jwt_user->data->display_name,
	);
}

/**
 * Validate REST request callback.
 *
 * @return array
 */
function http_bridge_jwt_validate() {
	global $http_bridge_jwt_user;

	$token = http_bridge_jwt_authorization();

	return array(
		'token'        => $token,
		'user_email'   => $http_bridge_jwt_user->data->user_email,
		'user_login'   => $http_bridge_jwt_user->data->user_login,
		'display_name' => $http_bridge_jwt_user->data->display_name,
	);
}

/**
 * Performs auth requests permisison checks.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return boolean
 */
function http_bridge_jwt_auth_permission_callback( $request ) {
	$data = $request->get_json_params();

	if ( null === $data ) {
		return new WP_Error( 'rest_bad_request', __( 'Invalid JSON data', 'http-bridge' ), array( 'status' => 400 ) );
	}

	if ( ! ( isset( $data['username'] ) && isset( $data['password'] ) ) ) {
		return new WP_Error( 'rest_bad_request', __( 'Missing login credentials', 'http-bridge' ), array( 'status' => 400 ) );
	}

	$user = wp_authenticate( $data['username'], $data['password'] );
	if ( is_wp_error( $user ) ) {
		return new WP_Error( 'rest_unauthorized', __( 'Invalid credentials', 'http-bridge' ), array( 'status' => 401 ) );
	}

	global $http_bridge_jwt_user;
	$http_bridge_jwt_user = $user;

	return true;
}

/**
 * Performs validation requests permission checks.
 *
 * @return boolean
 */
function http_bridge_jwt_validate_permission_callback() {
	try {
		$token = http_bridge_jwt_authorization();
	} catch ( Exception ) {
		return new WP_Error( 'rest_unauthorized', __( 'Invalid credentials', 'http-bridge' ), array( 'status' => 401 ) );
	}

	try {
		$payload = ( new JWT() )->decode( $token );
	} catch ( Exception ) {
		return new WP_Error( 'rest_unauthorized', __( 'Invalid authorization token', 'http-bridge' ), array( 'status' => 401 ) );
	} catch ( Error ) {
		return new WP_Error( 'rest_internal_server_error', __( 'Invalid authorization token', 'http-bridge' ), array( 'status' => 500 ) );
	}

	if ( get_bloginfo( 'url' ) !== $payload['iss'] ) {
		return new WP_Error( 'rest_unauthorized', __( 'The iss do not match with this server', 'http-bridge' ), array( 'status' => 401 ) );
	}

	$now = time();
	if ( $payload['exp'] <= $now ) {
		return new WP_Error( 'rest_unauthorized', __( 'The token is expired', 'http-bridge' ), array( 'status' => 401 ) );
	}

	if ( $payload['nbf'] >= $now ) {
		return new WP_Error( 'rest_unauthorized', __( 'The token is not valid yet', 'http-bridge' ), array( 'status' => 401 ) );
	}

	if ( ! isset( $payload['data']['user_id'] ) ) {
		return new WP_Error( 'rest_unauthorized', __( 'User ID not found in the token', 'http-bridge' ), array( 'status' => 401 ) );
	}

	global $http_bridge_jwt_user;
	$http_bridge_jwt_user = get_user_by( 'ID', (int) $payload['data']['user_id'] );

	return true;
}

/**
 * Determine current user from bearer authentication.
 *
 * @param int|null $user_id Already identified user ID.
 *
 * @return int|null Identified user ID.
 */
function http_bridge_jwt_determine_current_user( $user_id ) {
	$rest_api_slug = rest_get_url_prefix();
	$requested_url = isset( $_SERVER['REQUEST_URI'] )
		? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) )
		: '';

	$is_rest_request =
		( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
		strpos( $requested_url, $rest_api_slug );

	if ( $is_rest_request && $user_id ) {
		return $user_id;
	}

	$validate_uri = strpos( $requested_url, 'http-bridge/v1/jwt/validate' );

	if ( $validate_uri > 0 ) {
		return $user_id;
	}

	try {
		$auth = http_bridge_jwt_authorization();
	} catch ( Exception ) {
		return $user_id;
	}

	try {
		$payload = ( new JWT() )->decode( $auth );
	} catch ( Exception $e ) {
		if ( $e->getMessage() === 'Invalid token format' ) {
			global $http_bridge_jwt_auth_error;
			$http_bridge_jwt_auth_error = new WP_Error(
				'rest_unauthorized',
				__( 'Invalid token format', 'http-bridge' ),
				array( 'status' => 401 ),
			);
		}

		return $user_id;
	} catch ( Error ) {
		return $user_id;
	}

	return (int) $payload['data']['user_id'];
}

/**
 * Abort rest dispatches if auth errors.
 *
 * @param mixed $result Pre hook previous result.
 *
 * @return mixed|WP_Error Returns a WP_Error if authorization has failed.
 */
function http_bridge_jwt_rest_pre_dispatch( $result ) {
	global $http_bridge_jwt_auth_error;

	if ( is_wp_error( $result ) || is_wp_error( $http_bridge_jwt_auth_error ) ) {
		return $http_bridge_jwt_auth_error;
	}

	return $result;
}
