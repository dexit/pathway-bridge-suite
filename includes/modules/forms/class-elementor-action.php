<?php
/**
 * Elementor Form Action
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Modules\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Custom Elementor Pro Form Action with full lifecycle support.
 */
class Elementor_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {

	public function get_name() {
		return 'pathway_bridge';
	}

	public function get_label() {
		return __( 'Pathway Bridge', 'pathway-bridge-suite' );
	}

	public function run( $record, $ajax_handler ) {
		// Elementor Pro lifecycle: this is called during submission.
		// Since we already have a 'new_record' hook in Elementor_Bridge,
		// we can use this to explicitly trigger or validate the submission.

		// If we want to ensure it only runs once per submission:
		// We could move the logic here, but 'new_record' is more robust for background capture.
	}

	public function register_settings_section( $widget ) {
		$widget->start_controls_section(
			'section_pathway_bridge',
			[
				'label' => __( 'Pathway Bridge', 'pathway-bridge-suite' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$widget->add_control(
			'pathway_bridge_info',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw' => __( 'This form will be routed through the Pathway Bridge Suite workflow engine.', 'pathway-bridge-suite' ),
			]
		);

		$widget->end_controls_section();
	}

	public function on_export( $element ) {
		return $element;
	}
}
