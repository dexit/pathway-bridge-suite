<?php
/**
 * Vtiger Contacts template.
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'       => __( 'Contacts', 'forms-bridge' ),
	'description' => __(
		'Contact form bridge template. The resulting bridge will convert form submissions into Vtiger contacts.',
		'forms-bridge'
	),
	'fields'      => array(
		array(
			'ref'     => '#form',
			'name'    => 'title',
			'default' => __( 'Contacts', 'forms-bridge' ),
		),
		array(
			'ref'   => '#bridge',
			'name'  => 'endpoint',
			'value' => 'Contacts',
		),
		array(
			'ref'     => '#bridge/custom_fields[]',
			'name'    => 'assigned_user_id',
			'label'   => __( 'Assigned User', 'forms-bridge' ),
			'type'    => 'select',
			'options' => array(
				'endpoint' => 'Users',
				'finger'   => array(
					'value' => 'result[].id',
					'label' => 'result[].user_name',
				),
			),
		),
		array(
			'ref'     => '#bridge/custom_fields[]',
			'name'    => 'leadsource',
			'label'   => __( 'Lead Source', 'forms-bridge' ),
			'type'    => 'select',
			'options' => array(
				array(
					'value' => 'Web Site',
					'label' => __( 'Web Site', 'forms-bridge' ),
				),
				array(
					'value' => 'Cold Call',
					'label' => __( 'Cold Call', 'forms-bridge' ),
				),
				array(
					'value' => 'Direct Mail',
					'label' => __( 'Direct Mail', 'forms-bridge' ),
				),
				array(
					'value' => 'Existing Customer',
					'label' => __( 'Existing Customer', 'forms-bridge' ),
				),
				array(
					'value' => 'Employee',
					'label' => __( 'Employee', 'forms-bridge' ),
				),
				array(
					'value' => 'Partner',
					'label' => __( 'Partner', 'forms-bridge' ),
				),
				array(
					'value' => 'Public Relations',
					'label' => __( 'Public Relations', 'forms-bridge' ),
				),
				array(
					'value' => 'Word of Mouth',
					'label' => __( 'Word of Mouth', 'forms-bridge' ),
				),
				array(
					'value' => 'Conference',
					'label' => __( 'Conference', 'forms-bridge' ),
				),
				array(
					'value' => 'Other',
					'label' => __( 'Other', 'forms-bridge' ),
				),
			),
			'default' => 'Web Site',
		),
	),
	'bridge'      => array(
		'endpoint' => 'Contacts',
		'method'   => 'create',
		'workflow' => array( 'skip-contact' ),
	),
	'form'        => array(
		'fields' => array(
			array(
				'label'    => __( 'First Name', 'forms-bridge' ),
				'name'     => 'firstname',
				'type'     => 'text',
				'required' => true,
			),
			array(
				'label'    => __( 'Last Name', 'forms-bridge' ),
				'name'     => 'lastname',
				'type'     => 'text',
				'required' => true,
			),
			array(
				'label'    => __( 'Email', 'forms-bridge' ),
				'name'     => 'email',
				'type'     => 'email',
				'required' => true,
			),
			array(
				'label' => __( 'Phone', 'forms-bridge' ),
				'name'  => 'phone',
				'type'  => 'tel',
			),
			array(
				'label' => __( 'Address', 'forms-bridge' ),
				'name'  => 'mailingstreet',
				'type'  => 'text',
			),
			array(
				'label' => __( 'City', 'forms-bridge' ),
				'name'  => 'mailingcity',
				'type'  => 'text',
			),
			array(
				'label' => __( 'Postal Code', 'forms-bridge' ),
				'name'  => 'mailingzip',
				'type'  => 'text',
			),
			array(
				'label' => __( 'State', 'forms-bridge' ),
				'name'  => 'mailingstate',
				'type'  => 'text',
			),
			array(
				'label' => __( 'Country', 'forms-bridge' ),
				'name'  => 'mailingcountry',
				'type'  => 'text',
			),
			array(
				'label' => __( 'Description', 'forms-bridge' ),
				'name'  => 'description',
				'type'  => 'textarea',
			),
		),
	),
);
