<?php
/**
 * Posts Bridge Module
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Modules\Posts;

use PATHWAY_BRIDGE_SUITE\Registry;
use PATHWAY_BRIDGE_SUITE\Workflow_Engine;
use PATHWAY_BRIDGE_SUITE\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Module for synchronizing WordPress posts and handling workflow transitions.
 */
class Posts_Module {

	public const POST_TYPE = 'pbs-post-bridge';

	public function __construct() {
		$this->init();
		Registry::get_instance()->register( 'posts', $this );
	}

	private function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'save_post', array( $this, 'handle_post_save' ), 10, 3 );

		// VIP Workflow Integration
		add_action( 'vw_notification_send_to_webhook_payload', array( $this, 'handle_vip_workflow_transition' ) );
	}

	public function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Posts Bridges', 'pathway-bridge-suite' ),
			),
			'public' => false,
			'show_ui' => true,
			'supports' => array( 'title' ),
		) );
	}

	/**
	 * Handle VIP Workflow Plugin status transitions.
	 */
	public function handle_vip_workflow_transition( $payload ) {
		Logger::log( "VIP Workflow transition detected: " . ($payload['type'] ?? 'unknown'), Logger::INFO );

		// Map VIP payload to Bridge payload
		$bridge_payload = array(
			'event'     => $payload['type'] ?? 'post-update',
			'timestamp' => $payload['timestamp'] ?? time(),
			'data'      => $payload['data'] ?? '',
		);

		// Find bridges interested in 'vip-workflow'
		$bridges = get_posts( array(
			'post_type' => self::POST_TYPE,
			'meta_key' => '_pbs_source_event',
			'meta_value' => 'vip-workflow',
			'posts_per_page' => -1,
		) );

		foreach ( $bridges as $bridge ) {
			$jobs = get_post_meta( $bridge->ID, '_pbs_workflow_jobs', true ) ?: array();
			Workflow_Engine::get_instance()->execute( $bridge_payload, $jobs, $this, $bridge->ID );
		}

		return $payload; // Return original payload for VIP plugin to continue
	}

	public function handle_post_save( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$bridges = get_posts( array(
			'post_type' => self::POST_TYPE,
			'meta_key' => '_pbs_source_post_type',
			'meta_value' => $post->post_type,
			'posts_per_page' => -1,
		) );

		foreach ( $bridges as $bridge ) {
			$this->process_bridge( $bridge->ID, $post_id );
		}
	}

	public function process_bridge( $bridge_id, $post_id = null ) {
		$jobs = get_post_meta( $bridge_id, '_pbs_workflow_jobs', true ) ?: array();
		
		if ( $post_id ) {
			$payload = $this->prepare_post_payload( $post_id );
			return Workflow_Engine::get_instance()->execute( $payload, $jobs, $this, $bridge_id );
		}
	}

	private function prepare_post_payload( $post_id ) {
		$post = get_post( $post_id );
		return array(
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'status'  => $post->post_status,
			'meta'    => get_post_meta( $post_id ),
		);
	}
}

new Posts_Module();
