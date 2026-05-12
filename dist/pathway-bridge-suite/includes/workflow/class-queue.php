<?php
/**
 * Workflow Queue Handler
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Workflow;

use PATHWAY_BRIDGE_SUITE\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Handles delayed or queued execution of workflows.
 */
class Queue {

	public const OPTION_NAME = 'pbs_workflow_queue';

	public static function init() {
		if ( ! wp_next_scheduled( 'pbs_process_queue' ) ) {
			wp_schedule_event( time(), 'every_minute', 'pbs_process_queue' );
		}
		add_action( 'pbs_process_queue', array( __CLASS__, 'process' ) );
	}

	public static function add( $payload, $jobs, $bridge_id, $module_name ) {
		$queue = get_option( self::OPTION_NAME, array() );
		$queue[] = array(
			'payload' => $payload,
			'jobs'    => $jobs,
			'bridge'  => $bridge_id,
			'module'  => $module_name,
			'time'    => time(),
		);
		update_option( self::OPTION_NAME, $queue );
		Logger::log( "Job added to queue.", Logger::INFO );
	}

	public static function process() {
		$queue = get_option( self::OPTION_NAME, array() );
		if ( empty( $queue ) ) return;

		$item = array_shift( $queue );
		update_option( self::OPTION_NAME, $queue );

		Logger::log( "Processing queued job...", Logger::INFO );

		$engine = \PATHWAY_BRIDGE_SUITE\Workflow_Engine::get_instance();
		$module = \PATHWAY_BRIDGE_SUITE\Registry::get_instance()->get( $item['module'] );

		if ( $module ) {
			$engine->execute( $item['payload'], $item['jobs'], $module );
		}
	}
}

// Initialize Queue on plugins_loaded
add_action( 'plugins_loaded', array( 'PATHWAY_BRIDGE_SUITE\Workflow\Queue', 'init' ) );
