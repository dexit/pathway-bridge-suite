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
 * Custom Elementor Pro Form Action.
 */
class Elementor_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {

	public function get_name() {
		return 'pathway_bridge';
	}

	public function get_label() {
		return __( 'Pathway Bridge', 'pathway-bridge-suite' );
	}

	public function run( $record, $ajax_handler ) {
		// The capture_submission hook already handles this,
		// but we can use this to provide specific feedback or ensure execution order.
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
				'raw' => __( 'Submissions will be automatically routed through the Pathway Bridge Suite workflow.', 'pathway-bridge-suite' ),
			]
		);

		$widget->end_controls_section();
	}

	public function on_export( $element ) {
		return $element;
	}
}
