<?php
/**
 * Grist addon hooks
 *
 * @package postsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

add_filter(
	'posts_bridge_bridge_schema',
	function ( $schema, $addon ) {
		if ( 'grist' !== $addon ) {
			return $schema;
		}

		$schema['properties']['foreign_key']['const']     = 'id';
		$schema['properties']['endpoint']['default']      = '/v0/{baseId}/{tableName}/records';
		$schema['properties']['single_endpoint']['const'] = '/v0/{baseId}/{tableName}/records';
		$schema['properties']['backend']['default']       = 'Grist API';
		$schema['properties']['method']['const']          = 'GET';

		return $schema;
	},
	10,
	2
);

/**
 * Registers Grist http defaults.
 *
 * @param array $setting Http setting data.
 *
 * @return array
 */
function posts_bridge_grist_register_http_defaults( $setting ) {
	$addon = PBAPI::get_addon( 'grist' );
	if ( ! $addon->enabled ) {
		return $setting;
	}

	$backend_name = 'Grist API';
	$backend_url  = 'https://docs.getgrist.com';

	$credential_name   = 'Grist API key';
	$credential_schema = 'Bearer';

	$backends    = $setting['backends'] ?? array();
	$credentials = $setting['credentials'] ?? array();

	$urls   = array_column( $backends, 'base_url' );
	$exists = array_search( $backend_url, $urls, true );

	if ( false === $exists ) {
		$names = array_column( $backends, 'base_url' );

		if ( ! in_array( $backend_name, $names, true ) ) {
			$backends[] = array(
				'name'       => $backend_name,
				'base_url'   => $backend_url,
				'credential' => $credential_name,
				'headers'    => array(
					array(
						'name'  => 'Content-Type',
						'value' => 'application/json',
					),
				),
			);

			$setting['backends'] = $backends;
		}

		$schemas = array_column( $credentials, 'schema' );
		$exists  = array_search( $credential_schema, $schemas, true );

		if ( false === $exists ) {
			$names = array_column( $credentials, 'name' );

			if ( ! in_array( $credential_name, $names, true ) ) {
				$credentials[] = array(
					'name'         => $credential_name,
					'schema'       => $credential_schema,
					'access_token' => 'your-api-key',
					'expires_at'   => time() + 60 * 60 * 24 * 365 * 100,
				);

				$setting['credentials'] = $credentials;
			}
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
				$setting['default'] = posts_bridge_grist_register_http_defaults( $setting['default'] );
			}
		}

		return $settings;
	},
	20,
	2
);

add_filter(
	'option_posts-bridge_http',
	function ( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		return posts_bridge_grist_register_http_defaults( $data );
	},
	20,
	1,
);
