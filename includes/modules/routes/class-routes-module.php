<?php
/**
 * Routes Bridge Module
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Modules\Routes;

use PATHWAY_BRIDGE_SUITE\Registry;
use PATHWAY_BRIDGE_SUITE\Workflow_Engine;
use PATHWAY_BRIDGE_SUITE\Workflow\Transformer;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Module for custom WP REST API Routes with Block Model support.
 */
class Routes_Module {

	public const POST_TYPE = 'pbs-route';

	public function __construct() {
		$this->init();
		Registry::get_instance()->register( 'routes', $this );
	}

	private function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'rest_api_init', array( $this, 'init_rest_server' ) );

		// Create Content Model paradigm: Register route meta for Block Binding
		add_action( 'init', array( $this, 'register_route_meta' ) );
	}

	public function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Routes Bridges', 'pathway-bridge-suite' ),
			),
			'public' => false,
			'show_ui' => true,
			'show_in_rest' => true, // Enabled for Block Editor / Create Content Model integration
			'supports' => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
			'menu_icon' => 'dashicons-rest-api',
		) );
	}

	public function register_route_meta() {
		register_post_meta( self::POST_TYPE, '_pbs_endpoint', array(
			'show_in_rest' => true,
			'single' => true,
			'type' => 'string',
		) );
		register_post_meta( self::POST_TYPE, '_pbs_method', array(
			'show_in_rest' => true,
			'single' => true,
			'type' => 'string',
		) );
	}

	public function init_rest_server() {
		require_once __DIR__ . '/class-rest-server.php';
		$server = new REST_Server();
		$server->register_routes();
	}

	public function process_request( $payload, $route_id ) {
		$mapping = get_post_meta( $route_id, '_pbs_mapping', true );
		if ( $mapping && is_array( $mapping ) ) {
			$payload = Transformer::map( $payload, $mapping );
		}

		$jobs = get_post_meta( $route_id, '_pbs_workflow_jobs', true ) ?: array();
		return Workflow_Engine::get_instance()->execute( $payload, $jobs, $this, $route_id );
	}
}

new Routes_Module();
