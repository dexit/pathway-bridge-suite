<?php
/**
 * Google Sheets addon hooks
 *
 * @package postsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

add_filter(
	'posts_bridge_bridge_schema',
	function ( $schema, $addon ) {
		if ( 'gsheets' !== $addon ) {
			return $schema;
		}

		$schema['properties']['endpoint']['default']        = '/v4/spreadsheets/{spreadsheet_id}';
		$schema['properties']['single_endpoint']['title']   = _x( 'Tab', 'Google spreadsheets', 'posts-bridge' );
		$schema['properties']['single_endpoint']['default'] = 'Sheet1';
		$schema['properties']['backend']['default']         = 'Sheets API';
		$schema['properties']['method']['const']            = 'GET';

		return $schema;
	},
	10,
	2
);

add_filter(
	'http_bridge_oauth_url',
	function ( $url, $verb ) {
		if ( false === strpos( $url, 'accounts.google.com' ) ) {
			return $url;
		}

		if ( 'auth' === $verb ) {
			return $url;
		}

		return "https://oauth2.googleapis.com/{$verb}";
	},
	10,
	2
);

/**
 * Registers Google Sheets http defaults.
 *
 * @param array $setting Http setting data.
 *
 * @return array
 */
function posts_bridge_gsheets_register_http_defaults( $setting ) {
	$addon = PBAPI::get_addon( 'gsheets' );
	if ( ! $addon->enabled ) {
		return $setting;
	}

	$backend_name = 'Sheets API';
	$backend_url  = 'https://sheets.googleapis.com';

	$credential_name   = 'Google OAuth Client';
	$credential_schema = 'OAuth';
	$credential_url    = 'https://accounts.google.com/o/oauth2/v2';

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

		foreach ( $credentials as $candidate ) {
			if ( $credential_schema === $candidate['schema'] ) {
				if ( $credential_url === $candidate['oauth_url'] ) {
					$credential = $candidate;
				}
			}
		}

		if ( ! isset( $credential ) ) {
			$names = array_column( $credentials, 'name' );

			if ( ! in_array( $credential_name, $names, true ) ) {
				$credentials[] = array(
					'name'          => $credential_name,
					'schema'        => $credential_schema,
					'client_id'     => 'your-google-client-id',
					'client_secret' => 'your-google-client-secret',
					'oauth_url'     => $credential_url,
					'scope'         => 'https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/spreadsheets',
					'access_token'  => '',
					'expires_at'    => 0,
					'refresh_token' => '',
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
				$setting['default'] = posts_bridge_gsheets_register_http_defaults( $setting['default'] );
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

		return posts_bridge_gsheets_register_http_defaults( $data );
	},
	20,
	1,
);
