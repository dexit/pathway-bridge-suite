<?php
/**
 * Forms Bridge Module
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Modules\Forms;

use PATHWAY_BRIDGE_SUITE\Registry;
use PATHWAY_BRIDGE_SUITE\Workflow_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Module for handling Form submissions.
 */
class Forms_Module {

	public const POST_TYPE = 'pbs-form-bridge';

	public function __construct() {
		$this->init();
		Registry::get_instance()->register( 'forms', $this );
	}

	private function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		
		// Load Integrations
		$this->load_integrations();
	}

	public function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Form Bridges', 'pathway-bridge-suite' ),
			),
			'public' => false,
			'show_ui' => false,
			'supports' => array( 'title', 'excerpt' ),
		) );
	}

	private function load_integrations() {
		// Elementor Integration
		if ( did_action( 'elementor_pro/init' ) || class_exists( '\ElementorPro\Plugin' ) ) {
			require_once __DIR__ . '/class-elementor-bridge.php';
			new Elementor_Bridge();
		}
	}

	/**
	 * Process a form submission.
	 *
	 * @param array  $payload Captured form data.
	 * @param string $form_id Provider-specific form ID.
	 * @param string $provider Slug of the form provider (e.g., 'elementor').
	 */
	public function process_submission( $payload, $form_id, $provider ) {
		// Find matching bridges for this form
		$bridges = get_posts( array(
			'post_type' => self::POST_TYPE,
			'meta_query' => array(
				array(
					'key' => '_pbs_form_id',
					'value' => $form_id,
				),
				array(
					'key' => '_pbs_provider',
					'value' => $provider,
				),
			),
		) );

		foreach ( $bridges as $bridge ) {
			$jobs = get_post_meta( $bridge->ID, '_pbs_workflow_jobs', true ) ?: array();
			Workflow_Engine::get_instance()->execute( $payload, $jobs, $this );
		}
	}
}

new Forms_Module();
