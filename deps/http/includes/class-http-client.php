<?php
/**
 * Class Http_Client
 *
 * @package httpbridge
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

namespace HTTP_BRIDGE;

use WP_Http;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once 'class-multipart.php';

/**
 * HTTP Client.
 */
class Http_Client {

	/**
	 * Fills request arguments with defaults.
	 *
	 * @param array $args Request arguments.
	 *
	 * @return array Request arguments with defaults.
	 */
	private static function default_args( $args = array() ) {
		foreach ( $args as $arg => $value ) {
			switch ( $arg ) {
				case 'data':
					if ( ! ( is_string( $value ) || is_array( $value ) ) ) {
						$args['data'] = array();
					}
					break;
				case 'params':
				case 'headers':
				case 'files':
					$args[ $arg ] = (array) $value;
			}
		}

		$args = array_merge(
			array(
				'params'  => array(),
				'data'    => array(),
				'headers' => array(),
				'files'   => array(),
			),
			(array) $args
		);

		if ( is_string( $args['headers'] ) ) {
			$args['headers'] = WP_Http::processHeaders( $args['headers'] );
		} elseif ( ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		} else {
			$args['headers'] = self::normalize_headers( $args['headers'] );
		}

		return $args;
	}

	/**
	 * Normalize HTTP header names to avoid duplications.
	 *
	 * @param array $headers HTTP headers.
	 *
	 * @return array Normalized HTTP headers.
	 */
	private static function normalize_headers( $headers ) {
		$normalized = array();
		foreach ( (array) $headers as $name => $value ) {
			if ( empty( $name ) || empty( $value ) ) {
				continue;
			}
			$name                = str_replace( '_', '-', $name );
			$name                = implode(
				'-',
				array_map(
					function ( $chunk ) {
						return ucfirst( $chunk );
					},
					explode( '-', trim( $name ) )
				)
			);
			$normalized[ $name ] = $value;
		}

		return $normalized;
	}

	/**
	 * Add query params to URLs.
	 *
	 * @param string $url Target URL.
	 * @param array  $params Associative array with query params.
	 *
	 * @return string URL with query params.
	 */
	private static function add_query_str( $url, $params ) {
		$parsed = wp_parse_url( $url );

		if ( isset( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
			$params = array_merge( $query, $params );
			$url    = preg_replace( '/\?.*$/', '', $url );
		}

		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		return $url;
	}

	/**
	 * Performs a HEAD request.
	 *
	 * @param string $url Target URL.
	 * @param array  $args WP_Http::request arguments.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public static function head( $url, $args = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		$args['method'] = 'HEAD';
		return static::do_request( $url, $args );
	}

	/**
	 * Performs a GET request.
	 *
	 * @param string $url Target URL.
	 * @param array  $args WP_Http::request arguments.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public static function get( $url, $args = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		$args['method'] = 'GET';
		return static::do_request( $url, $args );
	}

	/**
	 * Performs a POST request.
	 * If files were defined in args, content type is forced to multipart/form-data.
	 *
	 * @param string $url Target URL.
	 * @param array  $args WP_Http::request arguments.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public static function post( $url, $args = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		$args['method'] = 'POST';

		if ( is_array( $args['files'] ) && ! empty( $args['files'] ) ) {
			return static::do_multipart( $url, $args );
		}

		return static::do_request( $url, $args );
	}

	/**
	 * Performs a PUT request.
	 * If files were defined in args, content type is forced to multipart/form-data.
	 *
	 * @param string $url Target URL.
	 * @param array  $args WP_Http::request arguments.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public static function put( $url, $args = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		$args['method'] = 'PUT';

		if ( is_array( $args['files'] ) && ! empty( $args['files'] ) ) {
			return static::do_multipart( $url, $args );
		}

		return static::do_request( $url, $args );
	}

	/**
	 * Performs a PATCH request.
	 * If files were defined in args, content type is forced to multipart/form-data.
	 *
	 * @param string $url Target URL.
	 * @param array  $args WP_Http::request arguments.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public static function patch( $url, $args = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		$args['method'] = 'PATCH';

		if ( is_array( $args['files'] ) && ! empty( $args['files'] ) ) {
			return static::do_multipart( $url, $args );
		}

		return static::do_request( $url, $args );
	}

	/**
	 * Performs a DETELE request.
	 *
	 * @param string $url Target URL.
	 * @param array  $args WP_Http::request arguments.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public static function delete( $url, $args = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		$args['method'] = 'DELETE';
		return static::do_request( $url, $args );
	}

	/**
	 * Proxy to do_request with body encoded as multipart/form-data with binary
	 * files as fields.
	 *
	 * @param string $url Target URL.
	 * @param array  $args WP_Http::request arguments.
	 *
	 * @return array|WP_Error Request response.
	 */
	private static function do_multipart( $url, $args ) {
		$args = static::default_args( $args );

		$multipart = new Multipart();

		// Add body to the multipart data.
		if ( ! empty( $args['data'] ) ) {
			// If body is encoded, then try to decode before set to the multipart payload.
			if ( is_string( $args['data'] ) ) {
				$content_type = static::get_content_type( $args['headers'] );
				if ( 'application/json' === $content_type ) {
					$data = json_decode( $args['data'], JSON_UNESCAPED_UNICODE );
					$multipart->add_array( $data );
				} elseif ( 'application/x-www-form-urlencoded' === $content_type ) {
					parse_str( $args['data'], $data );
					$multipart->add_array( $data );
				} elseif ( 'multipart/form-data' === $content_type ) {
					$fields = Multipart::from( $args['data'] )->decode();
					foreach ( $fields as $field ) {
						if ( $field['filename'] ) {
							$multipart->add_file(
								$field['name'],
								$field['filename'],
								$field['content-type'],
								$field['value']
							);
						} else {
							$multipart->add_part(
								$field['name'],
								$field['value']
							);
						}
					}
				} else {
					return new WP_Error(
						'unkown_content_type',
						__(
							'Can\' append files to your payload due to an unkown Content-Type header',
							'http-bridge'
						),
						array(
							'data'    => $args['data'],
							'headers' => $args['headers'],
						)
					);
				}
			} else {
				// Treat body as array.
				$multipart->add_array( (array) $args['data'] );
			}
		}

		// Add files to the request payload data.
		foreach ( $args['files'] as $name => $path ) {
			if ( ! ( is_file( $path ) && is_readable( $path ) ) ) {
				continue;
			}

			$filename = basename( $path );
			$filetype = mime_content_type( $filename ) ?: 'application/octet-stream';

			$multipart->add_file( $name, $path, $filetype );
		}

		$args['headers']['Content-Type'] = $multipart->content_type();
		$args['data']                    = $multipart->data();

		return static::do_request( $url, $args );
	}

	/**
	 * Performs a request on top of WP_Http client
	 *
	 * @param string $url Target URL.
	 * @param array  $args WP_Http::request arguments.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	private static function do_request( $url, $args ) {
		$args = static::default_args( $args );
		$url  = static::add_query_str( $url, $args['params'] );
		unset( $args['params'] );

		$content_type = static::get_content_type( $args['headers'] );

		if ( ! in_array( $args['method'], array( 'HEAD', 'GET', 'DELETE' ), true ) ) {
			if ( ! is_string( $args['data'] ) ) {
				$data = $args['data'];
				if ( ! empty( $data ) ) {
					$mime_type = $content_type;

					if ( strstr( $mime_type, 'application/json' ) ) {
						$args['body'] = wp_json_encode(
							$data,
							JSON_UNESCAPED_UNICODE
						);
					} elseif (
						strstr( $mime_type, 'application/x-www-form-urlencoded' )
					) {
						$args['body'] = http_build_query( $data );
					} elseif ( strstr( $mime_type, 'multipart/form-data' ) ) {
						$multipart = new Multipart();
						$multipart->add_array( $data );
						$args['body']                    = $multipart->data();
						$args['headers']['Content-Type'] = $multipart->content_type();
					} else {
						return new WP_Error(
							'posts_bridge_unkown_content_type',
							__(
								'Content type is unkown. Please, encode request data as string before submit if working with custom content types',
								'http-bridge'
							),
							array(
								'content-type' => $content_type,
								'data'         => $args['data'],
							)
						);
					}
				}
			} else {
				$args['body'] = $args['data'];
			}
		} else {
			unset( $args['headers']['Content-Type'] );
		}

		unset( $args['data'] );
		unset( $args['files'] );

		$request = apply_filters(
			'http_bridge_request',
			array(
				'url'  => $url,
				'args' => $args,
			)
		);

		do_action( 'http_bridge_before_request', $request );
		$response = wp_remote_request( $request['url'], $request['args'] );

		if ( is_wp_error( $response ) ) {
			$response->add_data(
				array(
					'request' => $request,
				)
			);
		} else {
			$status = (int) $response['response']['code'];
			if ( $status >= 300 ) {
				$response = new WP_Error(
					'http_bridge_error',
					__( 'HTTP error response status code', 'http-bridge' ),
					array(
						'request'  => $request,
						'response' => $response,
					)
				);
			} else {
				$headers      = is_array( $response['headers'] ) ? $response['headers'] : $response['headers']->getAll();
				$content_type = static::get_content_type( $headers );
				if ( 'application/json' === $content_type ) {
					$data = json_decode( $response['body'], true );
				} elseif ( 'application/x-www-form-urlencoded' === $content_type ) {
					parse_str( $response['body'], $data );
				} elseif ( 'multipart/form-data' === $content_type ) {
					$data = Multipart::from( $response['body'] )->decode();
				} else {
					$data = null;
				}

				$response['data'] = $data;
			}
		}

		do_action( 'http_bridge_response', $response, $request );

		return $response;
	}

	/**
	 * Gets mime type from HTTP Content-Type header.
	 *
	 * @param array $headers HTTP headers.
	 *
	 * @return string|null Mime type value of the header.
	 */
	public static function get_content_type( $headers = array() ) {
		$headers      = self::normalize_headers( $headers );
		$content_type = $headers['Content-Type'] ?? null;

		if ( $content_type ) {
			$content_type = preg_replace( '/;.*$/', '', $content_type );
			return trim( strtolower( $content_type ) );
		}
	}
}
