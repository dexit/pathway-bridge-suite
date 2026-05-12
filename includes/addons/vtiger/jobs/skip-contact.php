<?php
/**
 * Vtiger skip contact if exists job
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'       => __( 'Skip contact', 'forms-bridge' ),
	'description' => __( 'Searches for existing contacts by name and skip duplications', 'forms-bridge' ),
	'method'      => 'forms_bridge_vtiger_skip_contact',
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
	),
	'output'      => array(
		array(
			'name'   => 'lastname',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'firstname',
			'schema' => array( 'type' => 'string' ),
		),
	),
);

/**
 * Look up existing contacts by name and skips bridge submission if found.
 *
 * @param array              $payload Bridge payload.
 * @param Vtiger_Form_Bridge $bridge Bridge object.
 */
function forms_bridge_vtiger_skip_contact( $payload, $bridge ) {
	$query = "SELECT id FROM Contacts WHERE email = '{$payload['email']}'";

	if ( isset( $payload['firstname'], $payload['lastname'] ) ) {
		$query .= " OR firstname = '{$payload['firstname']}' AND lastname = '{$payload['lastname']}';";
	}

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
		$result = forms_bridge_vtiger_create_contact( $payload, $bridge );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return;
	}

	return $payload;
}
