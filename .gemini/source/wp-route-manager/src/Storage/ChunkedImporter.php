<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Storage;

use WP_Route_Manager\DB\LogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Processes queued payloads in configurable chunks.
 *
 * On each run:
 *   1. Claim N pending items (chunk_size)
 *   2. For each item: load endpoint config → dispatch → mark complete/fail
 *   3. If more items remain, reschedule self
 *
 * Integrates with Action Scheduler when available, falls back to WP Cron.
 */
final class ChunkedImporter {

	private const HOOK       = 'wprm_process_queue_chunk';
	private const ITEM_HOOK  = 'wprm_process_queue_item';
	private const GROUP      = 'wp-route-manager';

	private PayloadQueue $queue;
	private LogRepository $logs;

	public function __construct() {
		$this->queue = new PayloadQueue();
		$this->logs  = new LogRepository();
	}

	public function register_hooks(): void {
		add_action( self::HOOK,      [ $this, 'process_chunk' ] );
		add_action( self::ITEM_HOOK, [ $this, 'process_item' ] );

		// Admin AJAX triggers.
		add_action( 'wp_ajax_wprm_run_queue_chunk', [ $this, 'ajax_run_chunk' ] );
		add_action( 'wp_ajax_wprm_release_held',    [ $this, 'ajax_release_held' ] );
		add_action( 'wp_ajax_wprm_delete_queue_item', [ $this, 'ajax_delete_item' ] );
		add_action( 'wp_ajax_wprm_clear_queue',     [ $this, 'ajax_clear_queue' ] );
	}

	// ── Processing ─────────────────────────────────────────────────────────────

	public function process_item( int $queue_id ): void {
		$item = $this->queue->get( $queue_id );
		if ( ! $item || $item->status === 'completed' ) {
			return;
		}

		$this->queue->update_status( $queue_id, 'processing' );

		$start   = microtime( true );
		$payload = json_decode( $item->payload, true ) ?: [];

		try {
			// Allow extensions to handle queue item processing.
			$result = apply_filters( 'wprm_queue_process_item', null, $item, $payload );

			// Default: fire a WP action so other plugins can respond.
			if ( $result === null ) {
				do_action( 'wprm_queue_item_process', $payload, $item );
				$result = [ 'status' => 'dispatched', 'hook' => 'wprm_queue_item_process' ];
			}

			$this->queue->complete( $queue_id, wp_json_encode( $result ) );

			// Log completion.
			$this->logs->record( [
				'endpoint_id'         => (int) $item->endpoint_id,
				'endpoint_slug'       => $item->endpoint_slug . ' [queue]',
				'method'              => 'QUEUE',
				'caller_ip'           => 'queue-processor',
				'request_body_parsed' => $payload,
				'response_code'       => 200,
				'response_body'       => wp_json_encode( $result ),
				'duration_ms'         => (int) round( ( microtime( true ) - $start ) * 1000 ),
			] );

		} catch ( \Throwable $e ) {
			$this->queue->fail( $queue_id, $e->getMessage() );
			$this->logs->record( [
				'endpoint_id'   => (int) $item->endpoint_id,
				'endpoint_slug' => $item->endpoint_slug . ' [queue-failed]',
				'method'        => 'QUEUE',
				'caller_ip'     => 'queue-processor',
				'response_code' => 500,
				'error'         => $e->getMessage(),
				'duration_ms'   => (int) round( ( microtime( true ) - $start ) * 1000 ),
			] );
		}
	}

	/** Process a chunk of N pending items. */
	public function process_chunk( int $chunk_size = 0 ): void {
		if ( $chunk_size <= 0 ) {
			$chunk_size = (int) get_option( 'wprm_queue_chunk_size', 20 );
		}

		$result = $this->queue->query( [ 'status' => 'pending', 'limit' => $chunk_size ] );

		foreach ( $result['items'] as $item ) {
			$this->process_item( (int) $item->id );
		}

		// If there are more items, schedule another chunk.
		if ( $result['total'] > $chunk_size ) {
			$this->schedule_chunk();
		}
	}

	/** Schedule a chunk run now (or with delay). */
	public function schedule_chunk( int $delay = 0 ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK, [], self::GROUP ) ) {
				as_schedule_single_action( time() + $delay, self::HOOK, [], self::GROUP );
			}
		} else {
			if ( ! wp_next_scheduled( self::HOOK ) ) {
				wp_schedule_single_event( time() + $delay, self::HOOK );
			}
		}
	}

	// ── AJAX handlers ──────────────────────────────────────────────────────────

	public function ajax_run_chunk(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$chunk_size = absint( $_POST['chunk_size'] ?? 20 );
		$this->process_chunk( $chunk_size );

		$counts = $this->queue->counts();
		wp_send_json_success( [ 'counts' => $counts ] );
	}

	public function ajax_release_held(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$released = $this->queue->release_all_held();
		$this->schedule_chunk();
		wp_send_json_success( [ 'released' => $released ] );
	}

	public function ajax_delete_item(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		$id = absint( $_POST['id'] ?? 0 );
		$this->queue->delete( $id );
		wp_send_json_success();
	}

	public function ajax_clear_queue(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		$status = sanitize_key( $_POST['status'] ?? '' );
		$this->queue->clear( $status );
		wp_send_json_success( $this->queue->counts() );
	}
}
