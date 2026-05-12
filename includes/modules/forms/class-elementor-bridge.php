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
 * Native Elementor Form integration with File Upload support.
 */
class Elementor_Bridge {

	public function __construct() {
		// Capture all submissions automatically
		add_action( 'elementor_pro/forms/new_record', array( $this, 'capture_submission' ), 10, 2 );

		// Register custom action for manual selection in Form Widget
		add_action( 'elementor_pro/forms/actions/register', array( $this, 'register_action' ) );
	}

	/**
	 * Capture Elementor Form submission.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler
	 */
	public function capture_submission( $record, $handler ) {
		$form_id   = $record->get_form_settings( 'id' );
		$form_name = $record->get_form_settings( 'form_name' );
		$fields    = $record->get( 'fields' );
		$meta      = $record->get( 'meta' );
		$files     = $record->get( 'files' ); // Capture uploaded files
		
		$payload = array(
			'_form_id'   => $form_id,
			'_form_name' => $form_name,
			'_timestamp' => time(),
			'fields'     => array(),
			'meta'       => $meta,
			'files'      => array(),
		);

		foreach ( $fields as $id => $field ) {
			$payload['fields'][ $id ] = $field['value'];
			$payload[ $id ] = $field['value'];
		}

		if ( ! empty( $files ) ) {
			foreach ( $files as $id => $file_data ) {
				$payload['files'][ $id ] = array(
					'path' => $file_data['path'] ?? '',
					'url'  => $file_data['url'] ?? '',
					'type' => $file_data['type'] ?? '',
					'name' => $file_data['name'] ?? '',
				);
				// Also include URL in main payload for easy mapping
				$payload[ $id . '_url' ] = $file_data['url'] ?? '';
			}
		}

		// Send to Forms Module for processing
		$module = \PATHWAY_BRIDGE_SUITE\Registry::get_instance()->get( 'forms' );
		if ( $module ) {
			$module->process_submission( $payload, $form_id, 'elementor' );
		}
	}

	public function register_action( $registrar ) {
		require_once __DIR__ . '/class-elementor-action.php';
		$registrar->register( new Elementor_Action() );
	}
}
