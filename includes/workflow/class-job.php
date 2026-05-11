<?php
/**
 * Workflow Job Base
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Workflow;

use WP_Error;
use WP_Post;
use ReflectionFunction;
use ParseError;
use Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * A job is a link in a workflow chain that performs mutations or side effects.
 */
class Job {

	public const TYPE = 'pbs-job';

	protected $module;
	private $id;
	private $data;
	private $next = null;

	public function __construct( $data, $module ) {
		if ( $data instanceof WP_Post ) {
			$data = self::data_from_post( $data );
		}

		$this->module = $module;
		$this->data   = $this->validate( $data );

		if ( ! is_wp_error( $this->data ) ) {
			$this->id = $module . '-' . $data['name'];
		}
	}

	public function __get( $name ) {
		switch ( $name ) {
			case 'id':
				return $this->id;
			case 'module':
				return $this->module;
			case 'next':
				return $this->next;
			case 'data':
				return $this->data;
			case 'is_valid':
				return ! is_wp_error( $this->data );
			default:
				return $this->data[ $name ] ?? null;
		}
	}

	public function chain( $job ) {
		$this->next = $job;
	}

	public function run( $payload, $bridge ) {
		$method  = $this->method;
		
		if ( ! function_exists( $method ) ) {
			return $payload;
		}

		try {
			$payload = $method( $payload, $bridge, $this );
		} catch ( \Exception $e ) {
			return new WP_Error( 'job_execution_failed', $e->getMessage() );
		}

		if ( $this->next ) {
			return $this->next->run( $payload, $bridge );
		}

		return $payload;
	}

	private function validate( $data ) {
		// Simplified validation for now, will implement full schema check later
		if ( ! isset( $data['name'] ) ) {
			return new WP_Error( 'missing_name', 'Job name is required' );
		}
		
		if ( ! isset( $data['method'] ) ) {
			$data['method'] = function ( $p ) { return $p; };
		}

		return $data;
	}

	private static function data_from_post( $post ) {
		return array(
			'name'        => $post->post_name,
			'title'       => $post->post_title,
			'description' => $post->post_excerpt,
			'snippet'     => $post->post_content,
			'post_id'     => $post->ID,
		);
	}
}
