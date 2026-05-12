<?php
/**
 * Class Holded_Post_Bridge
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Post bridge implamentation for the REST API protocol.
 */
class Holded_Post_Bridge extends Post_Bridge {

	/**
	 * Bridge constructor with addon name provisioning.
	 *
	 * @param array $data Bridge data.
	 */
	public function __construct( $data ) {
		parent::__construct( $data, 'holded' );
		$this->data['single_endpoint'] = rtrim( $data['endpoint'], '/' ) . '/{id}';
	}
}
