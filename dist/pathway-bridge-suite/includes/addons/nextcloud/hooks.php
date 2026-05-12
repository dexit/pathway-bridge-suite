<?php
/**
 * Nextcloud addon hooks
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

add_filter(
	'posts_bridge_bridge_schema',
	function ( $schema, $addon ) {
		if ( 'nextcloud' !== $addon ) {
			return $schema;
		}

		$schema['properties']['endpoint']['pattern']     = '.+\.csv$';
		$schema['properties']['endpoint']['title']       = __( 'Filepath', 'posts-bridge' );
		$schema['properties']['endpoint']['description'] = __(
			'Path to the CSV file from the root of your nextcloud file system directory',
			'posts-bridge'
		);

		$schema['properties']['single_endpoint']['const'] = '-';

		$schema['properties']['method']['enum']  = array( 'GET', 'PUT', 'DELETE', 'MOVE', 'MKCOL', 'PROPFIND' );
		$schema['properties']['method']['const'] = 'GET';

		return $schema;
	},
	10,
	2
);
