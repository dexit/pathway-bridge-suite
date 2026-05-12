<?php
/**
 * Dolibarr addon hooks
 *
 * @package postsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

add_filter(
	'posts_bridge_bridge_schema',
	function ( $schema, $addon ) {
		if ( 'dolibarr' !== $addon ) {
			return $schema;
		}

		$schema['properties']['endpoint']['default']      = '/api/index.php/products';
		$schema['properties']['single_endpoint']['const'] = '/api/index.php/products/{id}';
		$schema['properties']['foreign_key']['const']     = 'id';
		$schema['properties']['method']['const']          = 'GET';

		return $schema;
	},
	10,
	2
);
