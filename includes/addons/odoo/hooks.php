<?php
/**
 * Odoo addon hooks
 *
 * @package postsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

add_filter(
	'posts_bridge_bridge_schema',
	function ( $schema, $addon ) {
		if ( 'odoo' !== $addon ) {
			return $schema;
		}

		$schema['properties']['endpoint']['title']   = __( 'Model', 'posts-bridge' );
		$schema['properties']['endpoint']['default'] = 'res.partner';

		$schema['properties']['single_endpoint']['const'] = '-';

		$schema['properties']['foreign_key']['const'] = 'id';

		$schema['properties']['method']['enum'] = array(
			'search',
			'read',
			'search_read',
			'fields_get',
		);

		$schema['properties']['method']['const'] = 'read';

		return $schema;
	},
	10,
	2
);
