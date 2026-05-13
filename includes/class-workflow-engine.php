<?php
/**
 * Workflow Engine
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE;

use PATHWAY_BRIDGE_SUITE\Workflow\Job;
use WPCT_PLUGIN\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Engine to orchestrate workflow execution.
 */
class Workflow_Engine extends Singleton {

	/**
	 * Run a workflow chain.
	 *
	 * @param array $payload Initial data.
	 * @param array $job_specs Array of job names/configs.
	 * @param mixed $bridge The bridge triggering the workflow.
	 * @param int   $bridge_id The ID of the specific bridge configuration.
	 *
	 * @return mixed Result of the workflow.
	 */
	public function execute( $payload, $job_specs, $bridge, $bridge_id = 0 ) {
		if ( empty( $job_specs ) ) {
			return $payload;
		}

		$module_name = Registry::get_instance()->get_key( $bridge );
		$log_id = Job_Manager::get_instance()->log_start( $bridge_id, $module_name, $payload );

		// Convert specs to a chain of Job instances
		$chain = $this->build_chain( $job_specs, $module_name );

		if ( ! $chain ) {
			Job_Manager::get_instance()->log_end( $log_id, $payload );
			return $payload;
		}

		$result = $chain->run( $payload, $bridge );

		Job_Manager::get_instance()->log_end( $log_id, $result );

		return $result;
	}

	private function build_chain( $specs, $module ) {
		$next = null;
		$specs = array_reverse( $specs );

		foreach ( $specs as $spec ) {
			$job = new Job( $spec, $module );
			$job->chain( $next );
			$next = $job;
		}

		return $next;
	}
}
