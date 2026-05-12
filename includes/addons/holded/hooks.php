<?php
/**
 * Holded addon hooks
 *
 * @package postsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

add_filter(
	'posts_bridge_bridge_schema',
	function ( $schema, $addon ) {
		if ( 'holded' !== $addon ) {
			return $schema;
		}

		$schema['properties']['endpoint']['default']      = '/api/invoicing/v1/contacts';
		$schema['properties']['single_endpoint']['const'] = '/api/invoicing/v1/contacts/{id}';
		$schema['properties']['foreign_key']['const']     = 'id';
		$schema['properties']['backend']['default']       = 'Holded API';
		$schema['properties']['method']['const']          = 'GET';

		return $schema;
	},
	10,
	2
);

/**
 * Default Holded http defaults.
 *
 * @param array $setting Http setting data.
 *
 * @return array
 */
function posts_bridge_holded_register_http_defaults( $setting ) {
	$addon = PBAPI::get_addon( 'holded' );
	if ( ! $addon->enabled ) {
		return $setting;
	}

	$backend_name = 'Holded API';
	$backend_url  = 'https://api.holded.com';

	$backends = $setting['backends'] ?? array();

	$urls   = array_column( $backends, 'base_url' );
	$exists = array_search( $backend_url, $urls, true );

	if ( false === $exists ) {
		$names = array_column( $backends, 'name' );

		if ( ! in_array( $backend_name, $names, true ) ) {
			$backends[] = array(
				'name'     => $backend_name,
				'base_url' => $backend_url,
				'headers'  => array(
					array(
						'name'  => 'Content-Type',
						'value' => 'application/json',
					),
					array(
						'name'  => 'key',
						'value' => 'your-holded-api-key',
					),
				),
			);

			$setting['backends'] = $backends;
		}
	}

	return $setting;
}

add_filter(
	'wpct_plugin_register_settings',
	function ( $settings, $group ) {
		if ( 'posts-bridge' !== $group ) {
			return $settings;
		}

		foreach ( $settings as &$setting ) {
			if ( 'http' === $setting['name'] ) {
				$setting['default'] = posts_bridge_holded_register_http_defaults( $setting['default'] );
				break;
			}
		}

		return $settings;
	},
	20,
	2,
);


add_filter(
	'option_posts-bridge_http',
	function ( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$data = posts_bridge_holded_register_http_defaults( $data ?? array() );
		return $data;
	},
	20,
	1
);
