<?php
/**
 * Vtiger Leads template.
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'       => __( 'Leads', 'forms-bridge' ),
	'description' => __(
		'Lead capture form template. The resulting bridge will convert form submissions into Vtiger leads.',
		'forms-bridge'
	),
	'fields'      => array(
		array(
			'ref'     => '#form',
			'name'    => 'title',
			'default' => __( 'Leads', 'forms-bridge' ),
		),
		array(
			'ref'   => '#bridge',
			'name'  => 'endpoint',
			'value' => 'Leads',
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
			'name'    => 'leadstatus',
			'label'   => __( 'Lead Status', 'forms-bridge' ),
			'type'    => 'select',
			'options' => array(
				array(
					'value' => 'Not Contacted',
					'label' => __( 'Not Contacted', 'forms-bridge' ),
				),
				array(
					'value' => 'Contacted',
					'label' => __( 'Contacted', 'forms-bridge' ),
				),
				array(
					'value' => 'Attempted to Contact',
					'label' => __( 'Attempted to Contact', 'forms-bridge' ),
				),
				array(
					'value' => 'Contact in Future',
					'label' => __( 'Contact in Future', 'forms-bridge' ),
				),
				array(
					'value' => 'Cold',
					'label' => __( 'Cold', 'forms-bridge' ),
				),
				array(
					'value' => 'Warm',
					'label' => __( 'Warm', 'forms-bridge' ),
				),
				array(
					'value' => 'Hot',
					'label' => __( 'Hot', 'forms-bridge' ),
				),
				array(
					'value' => 'Lost Lead',
					'label' => __( 'Lost Lead', 'forms-bridge' ),
				),
				array(
					'value' => 'Pre Qualified',
					'label' => __( 'Pre Qualified', 'forms-bridge' ),
				),
				array(
					'value' => 'Qualified',
					'label' => __( 'Junk Lead', 'forms-bridge' ),
				),
				array(
					'value' => 'Junk Lead',
					'label' => __( 'Junk Lead', 'forms-bridge' ),
				),
			),
			'default' => 'Not Contacted',
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
		'endpoint' => 'Leads',
		'method'   => 'create',
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
				'label' => __( 'Company', 'forms-bridge' ),
				'name'  => 'company',
				'type'  => 'text',
			),
			array(
				'label' => __( 'Designation', 'forms-bridge' ),
				'name'  => 'designation',
				'type'  => 'text',
			),
			array(
				'label' => __( 'Website', 'forms-bridge' ),
				'name'  => 'website',
				'type'  => 'url',
			),
			array(
				'label' => __( 'Message', 'forms-bridge' ),
				'name'  => 'description',
				'type'  => 'textarea',
			),
		),
	),
);
