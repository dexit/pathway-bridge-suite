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
 * Module for custom WP REST API Routes.
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
	}

	public function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Routes Bridges', 'pathway-bridge-suite' ),
			),
			'public' => false,
			'show_ui' => true,
			'supports' => array( 'title', 'editor', 'excerpt' ),
			'menu_icon' => 'dashicons-rest-api',
		) );
	}

	public function init_rest_server() {
		require_once __DIR__ . '/class-rest-server.php';
		$server = new REST_Server();
		$server->register_routes();
	}

	/**
	 * Process an incoming request for a specific route.
	 *
	 * @param array $payload Data from the REST request.
	 * @param int   $route_id The ID of the pbs-route post.
	 *
	 * @return mixed
	 */
	public function process_request( $payload, $route_id ) {
		// Apply DTO mapping first if defined
		$mapping = get_post_meta( $route_id, '_pbs_mapping', true );
		if ( $mapping && is_array( $mapping ) ) {
			$payload = Transformer::map( $payload, $mapping );
		}

		$jobs = get_post_meta( $route_id, '_pbs_workflow_jobs', true ) ?: array();

		// Execute workflow
		return Workflow_Engine::get_instance()->execute( $payload, $jobs, $this );
	}
}

new Routes_Module();
