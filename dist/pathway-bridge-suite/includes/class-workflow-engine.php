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
	 *
	 * @return mixed Result of the workflow.
	 */
	public function execute( $payload, $job_specs, $bridge ) {
		if ( empty( $job_specs ) ) {
			return $payload;
		}

		// Convert specs to a chain of Job instances
		$chain = $this->build_chain( $job_specs, $bridge->module );

		if ( ! $chain ) {
			return $payload;
		}

		return $chain->run( $payload, $bridge );
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
