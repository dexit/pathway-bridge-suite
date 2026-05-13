<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Api;

use WP_Route_Manager\DB\ApiKeyRepository;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Authenticator {

	private ApiKeyRepository $keys;

	public function __construct() {
		$this->keys = new ApiKeyRepository();
	}

	/**
	 * Validate the incoming API key against an endpoint.
	 *
	 * @return object|false  Key row on success, false on failure.
	 */
	public function validate( WP_REST_Request $request, int $endpoint_id ): object|false {
		// Accept from header or query param.
		$plain = $request->get_header( 'X-WRM-Key' )
			?: $request->get_param( 'api_key' );

		if ( empty( $plain ) ) {
			return false;
		}

		$key_row = $this->keys->validate( (string) $plain );

		if ( ! $key_row ) {
			return false;
		}

		if ( ! $this->keys->can_access_endpoint( $key_row, $endpoint_id ) ) {
			return false;
		}

		return $key_row;
	}

	/**
	 * Return the key ID from a key row (or 0 if no auth used).
	 */
	public function key_id( object|false $key_row ): int {
		return $key_row ? (int) $key_row->id : 0;
	}
}
