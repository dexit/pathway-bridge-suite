<?php
/**
 * Class Dolibarr_Post_Bridge
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Dolibarr post bridge
 */
class Dolibarr_Post_Bridge extends Post_Bridge {

	/**
	 * Bridge constructor.
	 *
	 * @param array $data Bridge data.
	 */
	public function __construct( $data ) {
		parent::__construct( $data, 'dolibarr' );
		$this->data['single_endpoint'] = rtrim( $data['endpoint'], '/' ) . '/{id}';
	}

	/**
	 * Performs a request to the bridge endpoint using the bridge backend and HTTP method.
	 *
	 * @param array $params Request params.
	 * @param array $headers HTTP headers.
	 *
	 * @return array|WP_Error Backend entries data.
	 */
	public function fetch_all( $params = array(), $headers = array() ) {
		if ( ! $this->is_valid ) {
			return new WP_Error( 'invalid_bridge', 'Bridge is invalid', (array) $this->data );
		}

		$endpoint = $this->endpoint();
		$params   = array_merge( $params, array( 'properties' => 'id' ) );
		$response = $this->request( $endpoint, $params, $headers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['data'];
	}
}
