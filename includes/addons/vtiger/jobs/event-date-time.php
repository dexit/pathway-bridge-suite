<?php
/**
 * Vtiger event date and time job
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'       => __( 'Event date and time', 'forms-bridge' ),
	'description' => __( 'Given a datetime and a duration, sets up the vtiger event dates', 'forms-bridge' ),
	'method'      => 'forms_bridge_vtiger_event_date_and_time',
	'input'       => array(
		array(
			'name'     => 'datetime',
			'schema'   => array( 'type' => 'string' ),
			'required' => true,
		),
		array(
			'name'     => 'duration_hours',
			'schema'   => array( 'type' => 'integer' ),
			'required' => true,
		),
		array(
			'name'     => 'duration_minutes',
			'schema'   => array( 'type' => 'string' ),
			'required' => true,
		),
	),
	'output'      => array(
		array(
			'name'   => 'date_start',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'time_start',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'due_date',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'time_end',
			'schema' => array( 'type' => 'string' ),
		),
		array(
			'name'   => 'duration_hours',
			'schema' => array( 'type' => 'integer' ),
		),
		array(
			'name'   => 'duration_minutes',
			'schema' => array( 'type' => 'string' ),
		),
	),
);

/**
 * Given a payload with a datetime and duration, sets up the vtiger event dates.
 *
 * @param array $payload Bridge payload.
 *
 * @return array
 */
function forms_bridge_vtiger_event_date_and_time( $payload ) {
	$datetime = $payload['datetime'];
	$time     = strtotime( $datetime );
	$endtime  = $time + $payload['duration_hours'] * 3600 + $payload['duration_minutes'] * 60;

	$payload['date_start'] = date( 'Y-m-d', $time );
	$payload['time_start'] = date( 'H:i:s', $time );
	$payload['due_date']   = date( 'Y-m-d', $endtime );
	$payload['time_end']   = date( 'H:i:s', $endtime );

	return $payload;
}
