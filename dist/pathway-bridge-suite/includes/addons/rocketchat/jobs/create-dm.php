<?php
/**
 * Rocket.Chat open direct message room job.
 *
 * @package formsbridge
 */

use FORMS_BRIDGE\Form_Bridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'       => __( 'Create DM', 'forms-bridge' ),
	'description' => __( 'Creates a direct message session with a user', 'forms-bridge' ),
	'method'      => 'forms_bridge_rocketchat_create_dm',
	'input'       => array(
		array(
			'name'     => 'username',
			'schema'   => array( 'type' => 'string' ),
			'required' => true,
		),
	),
	'output'      => array(
		array(
			'name'   => 'roomId',
			'schema' => array( 'type' => 'string' ),
		),
	),
);

/**
 * Open DM job method.
 *
 * @param array       $payload Bridge payload.
 * @param Form_Bridge $bridge Bridge object.
 *
 * @return array
 */
function forms_bridge_rocketchat_create_dm( $payload, $bridge ) {
	$response = $bridge->patch(
		array(
			'endpoint' => '/api/v1/dm.create',
			'method'   => 'POST',
		)
	)->submit( array( 'username' => $payload['username'] ) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$room_id           = $response['data']['room']['rid'];
	$payload['roomId'] = $room_id;

	return $payload;
}
