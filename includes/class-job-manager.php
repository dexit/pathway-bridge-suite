<?php
/**
 * Job Manager for persistent monitoring and retries
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE;

use WPCT_PLUGIN\Singleton;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Manages job logs, monitoring, and manual retries.
 */
class Job_Manager extends Singleton {

	public const POST_TYPE = 'pbs-job-log';

	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Job Logs', 'pathway-bridge-suite' ),
			),
			'public' => false,
			'show_ui' => true,
			'supports' => array( 'title', 'excerpt', 'custom-fields' ),
			'menu_icon' => 'dashicons-list-view',
		) );
	}

	public function log_start( $bridge_id, $module, $payload ) {
		$log_id = wp_insert_post( array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => sprintf( 'Job [%s] - %s', strtoupper( $module ), date( 'Y-m-d H:i:s' ) ),
			'post_status' => 'publish',
		) );

		if ( ! is_wp_error( $log_id ) ) {
			update_post_meta( $log_id, '_pbs_bridge_id', $bridge_id );
			update_post_meta( $log_id, '_pbs_module', $module );
			update_post_meta( $log_id, '_pbs_payload', $payload );
			update_post_meta( $log_id, '_pbs_status', 'running' );
		}

		return $log_id;
	}

	public function log_end( $log_id, $result ) {
		$status = is_wp_error( $result ) ? 'failed' : 'success';
		update_post_meta( $log_id, '_pbs_status', $status );
		update_post_meta( $log_id, '_pbs_result', $result );
		
		if ( $status === 'failed' ) {
			update_post_meta( $log_id, '_pbs_error', $result->get_error_message() );
		}
	}

	public function register_rest_routes() {
		register_rest_route( 'pathway-bridge/v1', '/jobs/retry/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_retry_job' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	public function rest_retry_job( $request ) {
		$log_id = $request['id'];
		$payload = get_post_meta( $log_id, '_pbs_payload', true );
		$bridge_id = get_post_meta( $log_id, '_pbs_bridge_id', true );
		$module_name = get_post_meta( $log_id, '_pbs_module', true );

		if ( empty( $payload ) || empty( $bridge_id ) ) {
			return new \WP_Error( 'missing_data', 'Cannot retry: missing payload or bridge ID', array( 'status' => 400 ) );
		}

		// Get the module instance
		$module = Registry::get_instance()->get( $module_name );
		if ( ! $module ) {
			return new \WP_Error( 'module_not_found', 'Module not found: ' . $module_name, array( 'status' => 404 ) );
		}

		// Re-process
		$result = $module->process_request( $payload, $bridge_id );

		return array(
			'success' => ! is_wp_error( $result ),
			'result'  => $result,
		);
	}
}
