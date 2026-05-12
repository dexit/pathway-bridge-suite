<?php
/**
 * Vtiger account job
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'       => __( 'Account', 'forms-bridge' ),
	'description' => __( 'Creates an account (organization) in Vtiger', 'forms-bridge' ),
	'method'      => 'forms_bridge_vtiger_create_account',
	'input'       => array(
		array(
			'name'     => 'accountname',
			'schema'   => array( 'type' => 'string' ),
			'required' => true,
		),
		array(
			'name'   => 'phone',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'otherphone',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'website',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'fax',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'tickersymbol',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'email1',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'email2',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'ownership',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'employees',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'account_id',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'rating',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'industry',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'siccode',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'accounttype',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'annual_revenue',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'emailoptout',
			'schema' => array( 'type' => 'boolean' ),
		),
		array(
			'name'   => 'notify_owner',
			'schema' => array( 'type' => 'boolean' ),
		),
		array(
			'name'   => 'assigned_user_id',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'bill_street',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'ship_street',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'bill_city',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'ship_city',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'bill_state',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'ship_state',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'bill_code',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'ship_code',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'bill_country',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'ship_country',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'bill_pobox',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'ship_pobox',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'description',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'starred',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'tags',
			'schema' => array( 'type' => 'string' ),
		),
	),
	'output'      => array(
		array(
			'name'   => 'account_id',
			'schema' => array( 'type' => 'string' ),
		),
	),
);

/**
 * Creates a new account and add its ID to the payload.
 *
 * @param array       $payload Bridge payload.
 * @param Form_Bridge $bridge Bridge object.
 *
 * @return array
 */
function forms_bridge_vtiger_create_account( $payload, $bridge ) {
	$account = array(
		'accountname' => $payload['accountname'],
	);

	$account_fields = array(
		'phone',
		'website',
		'fax',
		'tickersymbol',
		'otherphone',
		'account_id',
		'email1',
		'email2',
		'employees',
		'ownership',
		'rating',
		'industry',
		'siccode',
		'accounttype',
		'annual_revenue',
		'emailoptout',
		'notify_owner',
		'assigned_user_id',
		'bill_street',
		'ship_street',
		'bill_city',
		'ship_city',
		'bill_code',
		'ship_code',
		'bill_state',
		'ship_state',
		'bill_country',
		'ship_country',
		'bill_pobox',
		'ship_pobox',
		'description',
		'starred',
		'tags',
	);

	foreach ( $account_fields as $field ) {
		if ( isset( $payload[ $field ] ) ) {
			$account[ $field ] = $payload[ $field ];
		}
	}

	$query = "SELECT id FROM Accounts WHERE accountname = '{$account['accountname']}';";

	$response = $bridge->patch(
		array(
			'method'   => 'query',
			'endpoint' => 'Accounts',
		)
	)->submit( array( 'query' => $query ) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$account_id = $response['data']['result'][0]['id'];
	if ( ! empty( $account_id ) ) {
		$response = $bridge->patch(
			array(
				'method'   => 'update',
				'endpoint' => 'Accounts',
			)
		)->submit( array_merge( array( 'id' => $account_id ), $account ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$payload['account_id'] = $account_id;
		return $payload;
	}

	$response = $bridge->patch(
		array(
			'method'   => 'create',
			'endpoint' => 'Accounts',
		)
	)->submit( $account );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$payload['account_id'] = $response['data']['result']['id'];
	return $payload;
}
