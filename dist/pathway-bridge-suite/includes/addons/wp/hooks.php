<?php
/**
 * WordPress addon hooks
 *
 * @package postsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

add_filter(
	'posts_bridge_bridge_schema',
	function ( $schema, $addon ) {
		if ( 'wp' !== $addon ) {
			return $schema;
		}

		$schema['properties']['endpoint']['default']      = '/wp-json/wp/v2/posts';
		$schema['properties']['single_endpoint']['const'] = '/wp-json/wp/v2/posts/{id}';
		$schema['properties']['foreign_key']['const']     = 'id';
		$schema['properties']['method']['const']          = 'GET';

		return $schema;
	},
	10,
	2
);
