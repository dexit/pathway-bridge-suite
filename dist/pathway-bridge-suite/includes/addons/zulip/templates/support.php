<?php
/**
 * Zulip addon support stream bridge template
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'       => __( 'Support Stream', 'forms-bridge' ),
	'description' => __(
		'Support form template. The resulting bridge will notify form submissions in a Zulip stream',
		'forms-bridge'
	),
	'fields'      => array(
		array(
			'ref'   => '#bridge',
			'name'  => 'endpoint',
			'value' => '/api/v1/messages',
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
				'name'     => 'topic',
				'label'    => __( 'Topic', 'forms-bridge' ),
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
		'endpoint'      => '/api/v1/messages',
		'workflow'      => array( 'summary' ),
		'custom_fields' => array(
			array(
				'name'  => 'type',
				'value' => 'stream',
			),
		),
		'mutations'     => array(
			array(
				array(
					'from' => 'to[]',
					'to'   => 'to[]',
					'cast' => 'integer',
				),
				array(
					'from' => 'to',
					'to'   => 'to',
					'cast' => 'json',
				),
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
					'from' => '?comments',
					'to'   => 'fields.comments',
					'cast' => 'string',
				),
			),
		),
	),
);
