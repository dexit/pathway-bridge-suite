<?php

use HTTP_BRIDGE\Http_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Public function to perform a GET requests.
 *
 * @param string $url Target URL.
 * @param array  $params Query params.
 * @param array  $headers HTTP headers.
 * @param array  $args Request args.
 *
 * @return array|WP_Error Response data or error.
 */
function http_bridge_get( $url, $params = array(), $headers = array(), $args = array() ) {
	return Http_Client::get(
		$url,
		array_merge(
			$args,
			array(
				'params'  => $params,
				'headers' => $headers,
			)
		)
	);
}

/**
 * Public function to perform a POST requests.
 *
 * @param string $url Target URL.
 * @param array  $data Request payload.
 * @param array  $headers HTTP headers.
 * @param array  $files Request files.
 * @param array  $args Request args.
 *
 * @return array|WP_Error Response data or error.
 */
function http_bridge_post(
	$url,
	$data = array(),
	$headers = array(),
	$files = array(),
	$args = array()
) {
	return Http_Client::post(
		$url,
		array_merge(
			$args,
			array(
				'data'    => $data,
				'headers' => (array) $headers,
				'files'   => (array) $files,
			)
		)
	);
}

/**
 * Public function to perform a PUT requests.
 *
 * @param string $url Target URL.
 * @param array  $data Request payload.
 * @param array  $headers HTTP headers.
 * @param array  $files Request files.
 * @param array  $argss Request args.
 *
 * @return array|WP_Error Response data or error.
 */
function http_bridge_put(
	$url,
	$data = array(),
	$headers = array(),
	$files = array(),
	$args = array()
) {
	return Http_Client::put(
		$url,
		array_merge(
			$args,
			array(
				'data'    => $data,
				'headers' => (array) $headers,
				'files'   => (array) $files,
			)
		)
	);
}

/**
 * Public function to perform a PUT requests.
 *
 * @param string $url Target URL.
 * @param array  $data Request payload.
 * @param array  $headers HTTP headers.
 * @param array  $files Request files.
 * @param array  $argss Request args.
 *
 * @return array|WP_Error Response data or error.
 */
function http_bridge_patch(
	$url,
	$data = array(),
	$headers = array(),
	$files = array(),
	$args = array()
) {
	return Http_Client::patch(
		$url,
		array_merge(
			$args,
			array(
				'data'    => $data,
				'headers' => (array) $headers,
				'files'   => (array) $files,
			)
		)
	);
}

/**
 * Public function to perform a DELETE requests.
 *
 * @param string $url Target URL.
 * @param array  $params Query params.
 * @param array  $headers HTTP headers.
 * @param array  $args Request args.
 *
 * @return array|WP_Error Response data or error.
 */
function http_bridge_delete( $url, $params = array(), $headers = array(), $args = array() ) {
	return Http_Client::delete(
		$url,
		array_merge(
			$args,
			array(
				'params'  => $params,
				'headers' => $headers,
			)
		)
	);
}

/**
 * Public function to perform an HEAD requests.
 *
 * @param string $url Target URL.
 * @param array  $params Query params.
 * @param array  $headers HTTP headers.
 * @param array  $args Request args.
 *
 * @return array|WP_Error Response data or error.
 */
function http_bridge_head( $url, $params = array(), $headers = array(), $args = array() ) {
	return Http_Client::head(
		$url,
		array_merge(
			$args,
			array(
				'params'  => $params,
				'headers' => $headers,
			)
		)
	);
}
