<?php
/**
 * Vtiger contact job
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'       => __( 'Contact', 'forms-bridge' ),
	'description' => __( 'Creates a contact in Vtiger', 'forms-bridge' ),
	'method'      => 'forms_bridge_vtiger_create_contact',
	'input'       => array(
		array(
			'name'     => 'lastname',
			'schema'   => array( 'type' => 'string' ),
			'required' => true,
		),
		array(
			'name'   => 'firstname',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'salutationtype',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'leadsource',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'birthday',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'assigned_user_id',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'phone',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'mobile',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'homephone',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'otherphone',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'fax',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'email',
			'schema' => array( 'type' => 'boolean' ),
		),
		array(
			'name'   => 'secondaryemail',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'contact_id',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'account_id',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'title',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'department',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'assistant',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'assistantphone',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'donotcall',
			'schema' => array( 'type' => 'boolean' ),
		),
		array(
			'name'   => 'emailoptout',
			'schema' => array( 'type' => 'boolean' ),
		),
		array(
			'name'   => 'reference',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'notify_owner',
			'schema' => array( 'type' => 'boolean' ),
		),
		array(
			'name'   => 'portal',
			'schema' => array( 'type' => 'boolean' ),
		),
		array(
			'name'   => 'mailingstreet',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'otherstreet',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'mailingcity',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'othercity',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'mailingstate',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'otherstate',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'mailingzip',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'otherzip',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'mailingcountry',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'othercountry',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'mailingpobox',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'otherpobox',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'imagename',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'description',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'starred',
			'schema' => array( 'type' => 'boolean' ),
		),
		array(
			'name'   => 'tags',
			'schema' => array( 'type' => 'string' ),
		),
	),
	'output'      => array(
		array(
			'name'   => 'contact_id',
			'schema' => array( 'type' => 'string' ),
		),
	),
);

/**
 * Creates a new contact and add its ID to the payload.
 *
 * @param array              $payload Bridge payload.
 * @param Vtiger_Form_Bridge $bridge Bridge object.
 *
 * @return array
 */
function forms_bridge_vtiger_create_contact( $payload, $bridge ) {
	$contact = array(
		'lastname' => $payload['lastname'],
	);

	$contact_fields = array(
		'firstname',
		'salutationtype',
		'assigned_user_id',
		'phone',
		'mobile',
		'account_id',
		'homephone',
		'leadsource',
		'otherphone',
		'title',
		'fax',
		'department',
		'birthday',
		'email',
		'contact_id',
		'assistant',
		'secondaryemail',
		'assistantphone',
		'donotcall',
		'emailoptout',
		'reference',
		'notify_owner',
		'portal',
		'mailingstreet',
		'otherstreet',
		'mailingcity',
		'othercity',
		'mailingzip',
		'otherzip',
		'mailingstate',
		'otherstate',
		'mailingcountry',
		'othercountry',
		'mailingpobox',
		'otherpobox',
		'imagename',
		'description',
		'starred',
		'tags',
	);

	foreach ( $contact_fields as $field ) {
		if ( isset( $payload[ $field ] ) ) {
			$contact[ $field ] = $payload[ $field ];
		}
	}

	if ( isset( $contact['firstname'] ) ) {
		$query = "SELECT id FROM Contacts WHERE firstname = '{$contact['firstname']}' AND lastname = '{$contact['lastname']}'";

		if ( isset( $contact['email'] ) ) {
			$query .= " OR email = '{$contact['email']}';";
		} else {
			$query .= ';';
		}
	} elseif ( isset( $contact['email'] ) ) {
		$query = "SELECT id FROM Contacts WHERE email = '{$contact['email']}';";
	}

	if ( isset( $query ) ) {
		$response = $bridge->patch(
			array(
				'method'   => 'query',
				'endpoint' => 'Contacts',
			)
		)->submit( array( 'query' => $query ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$contact_id = $response['data']['result'][0]['id'] ?? null;
		if ( ! empty( $contact_id ) ) {
			$response = $bridge->patch(
				array(
					'name'     => '__vtiger-' . time(),
					'method'   => 'update',
					'endpoint' => 'Contacts',
				)
			)->submit( array_merge( array( 'id' => $contact_id ), $contact ) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$payload['contact_id'] = $contact_id;
			return $payload;
		}
	}

	$response = $bridge->patch(
		array(
			'name'     => '__vtiger-' . time(),
			'method'   => 'create',
			'endpoint' => 'Contacts',
		)
	)->submit( $contact );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$payload['contact_id'] = $response['data']['result']['id'];
	return $payload;
}
