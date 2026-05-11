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
 * Module for synchronizing WordPress posts with external systems.
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
		add_action( 'pbs_posts_sync_cron', array( $this, 'run_scheduled_sync' ) );
	}

	public function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Posts Bridges', 'pathway-bridge-suite' ),
			),
			'public' => false,
			'show_ui' => false,
			'supports' => array( 'title' ),
		) );
	}

	/**
	 * Triggered when a post is saved. Checks if any bridge is monitoring this post type.
	 */
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

	/**
	 * Run all bridges that have a schedule.
	 */
	public function run_scheduled_sync() {
		$bridges = get_posts( array(
			'post_type' => self::POST_TYPE,
			'posts_per_page' => -1,
		) );

		foreach ( $bridges as $bridge ) {
			// Logic to check if schedule is due
			$this->process_bridge( $bridge->ID );
		}
	}

	/**
	 * Process a specific bridge.
	 *
	 * @param int      $bridge_id ID of the pbs-post-bridge.
	 * @param int|null $post_id Optional post ID to sync only one.
	 */
	public function process_bridge( $bridge_id, $post_id = null ) {
		$jobs = get_post_meta( $bridge_id, '_pbs_workflow_jobs', true ) ?: array();
		
		if ( $post_id ) {
			$payload = $this->prepare_post_payload( $post_id );
			return Workflow_Engine::get_instance()->execute( $payload, $jobs, $this );
		} else {
			// Bulk sync logic
			$source_type = get_post_meta( $bridge_id, '_pbs_source_post_type', true );
			$posts = get_posts( array(
				'post_type' => $source_type,
				'posts_per_page' => -1,
			) );

			foreach ( $posts as $p ) {
				$payload = $this->prepare_post_payload( $p->ID );
				Workflow_Engine::get_instance()->execute( $payload, $jobs, $this );
			}
		}
	}

	private function prepare_post_payload( $post_id ) {
		$post = get_post( $post_id );
		return array(
			'id' => $post->ID,
			'title' => $post->post_title,
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'status' => $post->post_status,
			'meta' => get_post_meta( $post_id ),
		);
	}
}

new Posts_Module();
