<?php
/**
 * Elementor Form Bridge
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Modules\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Native Elementor Form integration.
 */
class Elementor_Bridge {

	public function __construct() {
		add_action( 'elementor_pro/forms/new_record', array( $this, 'capture_submission' ), 10, 2 );
	}

	/**
	 * Capture Elementor Form submission.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler
	 */
	public function capture_submission( $record, $handler ) {
		$form_id = $record->get_form_settings( 'id' );
		$fields  = $record->get( 'fields' );
		
		$payload = array();
		foreach ( $fields as $id => $field ) {
			$payload[ $id ] = $field['value'];
		}

		// Send to Forms Module for processing
		$module = \PATHWAY_BRIDGE_SUITE\Registry::get_instance()->get( 'forms' );
		if ( $module ) {
			$module->process_submission( $payload, $form_id, 'elementor' );
		}
	}
}
