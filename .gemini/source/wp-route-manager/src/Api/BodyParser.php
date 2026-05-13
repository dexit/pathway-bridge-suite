<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Api;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class BodyParser {

	/**
	 * Parse the request body into an array.
	 *
	 * @param WP_REST_Request $request
	 * @param string          $mode        auto|json|form|raw|php
	 * @param int             $snippet_id  PHP snippet post ID (mode=php only)
	 * @param int             $endpoint_id For context passing to PHP snippet
	 * @return array{parsed: mixed[], raw: string}
	 */
	public function parse(
		WP_REST_Request $request,
		string $mode,
		int $snippet_id = 0,
		int $endpoint_id = 0
	): array {
		$raw = (string) $request->get_body();

		$parsed = match ( $mode ) {
			'json'  => $this->parse_json( $raw ),
			'form'  => $this->parse_form( $raw ),
			'raw'   => [ 'body' => $raw ],
			'php'   => $this->parse_php( $raw, $snippet_id, $request, $endpoint_id ),
			default => $this->parse_auto( $request, $raw ),
		};

		// Always merge query params (lower priority than body).
		$params = (array) $request->get_query_params();
		unset( $params['api_key'] ); // Never leak key into parsed data.

		$parsed = array_merge( $params, is_array( $parsed ) ? $parsed : [] );

		return [
			'parsed' => $parsed,
			'raw'    => $raw,
		];
	}

	/** @return mixed[] */
	private function parse_auto( WP_REST_Request $request, string $raw ): array {
		$content_type = strtolower( $request->get_content_type()['value'] ?? '' );

		if ( str_contains( $content_type, 'application/json' ) ) {
			return $this->parse_json( $raw );
		}

		if ( str_contains( $content_type, 'application/x-www-form-urlencoded' )
			|| str_contains( $content_type, 'multipart/form-data' ) ) {
			return $this->parse_form( $raw );
		}

		// Fall back to trying JSON, then form.
		$json = $this->parse_json( $raw );
		if ( ! empty( $json ) ) {
			return $json;
		}

		// WP REST might have already parsed it.
		$body_params = $request->get_body_params();
		if ( $body_params ) {
			return (array) $body_params;
		}

		return [ 'body' => $raw ];
	}

	/** @return mixed[] */
	private function parse_json( string $raw ): array {
		if ( empty( $raw ) ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : [ 'raw' => $raw ];
	}

	/** @return mixed[] */
	private function parse_form( string $raw ): array {
		$parsed = [];
		parse_str( $raw, $parsed );
		return $parsed ?: [];
	}

	/** @return mixed[] */
	private function parse_php( string $raw, int $snippet_id, WP_REST_Request $request, int $endpoint_id ): array {
		if ( ! WPRM_ALLOW_PHP_SNIPPETS || ! $snippet_id ) {
			return [ 'body' => $raw ];
		}

		$code = get_post_meta( $snippet_id, 'wprm_snippet_code', true );
		if ( empty( $code ) ) {
			return [ 'body' => $raw ];
		}

		try {
			$result = ( static function ( string $__code, string $raw, \WP_REST_Request $request, int $endpoint ): mixed {
				return eval( '?>' . $__code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
			} )( $code, $raw, $request, $endpoint_id );

			return is_array( $result ) ? $result : [ 'body' => $raw ];
		} catch ( \Throwable $e ) {
			return [ 'body' => $raw, '_parse_error' => $e->getMessage() ];
		}
	}
}
