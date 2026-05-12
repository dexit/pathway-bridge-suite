<?php
/**
 * Rocket.Chat addon support stream bridge template
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'       => __( 'Support Channel', 'forms-bridge' ),
	'description' => __(
		'Support form template. The resulting bridge will notify form submissions in a Rocket.Chat channel',
		'forms-bridge'
	),
	'fields'      => array(
		array(
			'ref'   => '#bridge',
			'name'  => 'endpoint',
			'value' => '/api/v1/chat.postMessage',
		),
		array(
			'ref'         => '#bridge/custom_fields[]',
			'name'        => 'roomId',
			'label'       => __( 'Channel', 'forms-bridge' ),
			'description' => __(
				'Name of the channel where messages will be sent',
				'forms-bridge'
			),
			'type'        => 'select',
			'options'     => array(
				'endpoint' => '/api/v1/rooms.get',
				'finger'   => array(
					'value' => 'update[]._id',
					'label' => 'update[].name',
				),
			),
			'required'    => true,
		),
		array(
			'ref'     => '#form',
			'name'    => 'title',
			'default' => __( 'Support', 'forms-bridge' ),
		),
	),
	'form'        => array(
		'title'  => __( 'Support', 'forms-bridge' ),
		'fields' => array(
			array(
				'name'     => 'your-name',
				'label'    => __( 'Your name', 'forms-bridge' ),
				'type'     => 'text',
				'required' => true,
			),
			array(
				'name'     => 'your-email',
				'label'    => __( 'Your email', 'forms-bridge' ),
				'type'     => 'email',
				'required' => true,
			),
			array(
				'name'     => 'subject',
				'label'    => __( 'Subject', 'forms-bridge' ),
				'type'     => 'select',
				'options'  => array(
					array(
						'value' => 'Option 1',
						'label' => 'Option 1',
					),
					array(
						'value' => 'Option 2',
						'label' => 'Option 2',
					),
				),
				'required' => true,
			),
			array(
				'name'  => 'comments',
				'label' => __( 'Comments', 'forms-bridge' ),
				'type'  => 'textarea',
			),
		),
	),
	'bridge'      => array(
		'endpoint'  => '/api/v1/chat.postMessage',
		'workflow'  => array( 'summary' ),
		'mutations' => array(
			array(
				array(
					'from' => 'your-name',
					'to'   => 'fields.name',
					'cast' => 'string',
				),
				array(
					'from' => 'your-email',
					'to'   => 'fields.email',
					'cast' => 'string',
				),
				array(
					'from' => 'subject',
					'to'   => 'fields.subject',
					'cast' => 'string',
				),
				array(
					'from' => '?comments',
					'to'   => 'fields.comments',
					'cast' => 'string',
				),
			),
		),
	),
);
