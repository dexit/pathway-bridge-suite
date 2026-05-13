<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the wprm_payload_queue table.
 *
 * Three processing modes:
 *   pending    → scheduled for async processing (Action Scheduler or WP Cron)
 *   held       → waiting for manual release by admin
 *   processing → currently being worked on
 *   completed  → done
 *   failed     → exhausted max_attempts
 */
final class PayloadQueue {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wprm_payload_queue';
	}

	/**
	 * Add a payload to the queue.
	 *
	 * @param string $status  'pending' = auto-dispatch, 'held' = manual release
	 */
	public function enqueue(
		int $endpoint_id,
		string $endpoint_slug,
		array $payload,
		string $status = 'pending',
		int $priority = 5,
		int $max_attempts = 3
	): int {
		global $wpdb;

		$wpdb->insert(
			$this->table(),
			[
				'endpoint_id'   => $endpoint_id ?: null,
				'endpoint_slug' => $endpoint_slug,
				'payload'       => wp_json_encode( $payload ),
				'status'        => $status,
				'priority'      => $priority,
				'max_attempts'  => $max_attempts,
				'scheduled_at'  => current_time( 'mysql' ),
				'created_at'    => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Schedule a queue item for processing.
	 * Uses Action Scheduler if available (WooCommerce), falls back to WP Cron.
	 */
	public function schedule_processing( int $queue_id, int $delay_seconds = 0 ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + $delay_seconds,
				'wprm_process_queue_item',
				[ 'queue_id' => $queue_id ],
				'wp-route-manager'
			);
		} else {
			wp_schedule_single_event(
				time() + $delay_seconds,
				'wprm_process_queue_item',
				[ $queue_id ]
			);
		}
	}

	/** @return array{items: object[], total: int} */
	public function query( array $args = [] ): array {
		global $wpdb;
		$table   = $this->table();
		$where   = [ '1=1' ];
		$prepare = [];

		if ( ! empty( $args['status'] ) ) {
			$where[]   = 'status = %s';
			$prepare[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['endpoint_id'] ) ) {
			$where[]   = 'endpoint_id = %d';
			$prepare[] = (int) $args['endpoint_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$limit     = min( 500, (int) ( $args['limit'] ?? 50 ) );
		$offset    = max( 0, (int) ( $args['offset'] ?? 0 ) );

		if ( $prepare ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $prepare ) ); // phpcs:ignore
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY priority ASC, scheduled_at ASC LIMIT %d OFFSET %d", array_merge( $prepare, [ $limit, $offset ] ) ) ) ?: []; // phpcs:ignore
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY priority ASC, scheduled_at ASC LIMIT %d OFFSET %d", [ $limit, $offset ] ) ) ?: []; // phpcs:ignore
		}

		return [ 'items' => $items, 'total' => $total ];
	}

	public function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ) ) ?: null;
	}

	public function complete( int $id, string $result = '' ): void {
		global $wpdb;
		$wpdb->update(
			$this->table(),
			[ 'status' => 'completed', 'completed_at' => current_time( 'mysql' ), 'result' => $result, 'error' => null ],
			[ 'id' => $id ],
			[ '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function fail( int $id, string $error ): void {
		global $wpdb;
		$item    = $this->get( $id );
		$attempts = $item ? (int) $item->attempts + 1 : 1;
		$max      = $item ? (int) $item->max_attempts : 3;
		$status   = $attempts >= $max ? 'failed' : 'pending';

		$wpdb->update(
			$this->table(),
			[ 'status' => $status, 'error' => $error, 'attempts' => $attempts, 'started_at' => null ],
			[ 'id' => $id ],
			[ '%s', '%s', '%d', '%s' ],
			[ '%d' ]
		);
	}

	public function update_status( int $id, string $status ): void {
		global $wpdb;
		$wpdb->update( $this->table(), [ 'status' => $status ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
	}

	/** Release all 'held' items to 'pending' for dispatch. */
	public function release_all_held(): int {
		global $wpdb;
		return (int) $wpdb->update( $this->table(), [ 'status' => 'pending' ], [ 'status' => 'held' ], [ '%s' ], [ '%s' ] );
	}

	public function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );
	}

	public function clear( string $status = '' ): int {
		global $wpdb;
		if ( $status ) {
			return (int) $wpdb->delete( $this->table(), [ 'status' => $status ], [ '%s' ] );
		}
		return (int) $wpdb->query( "TRUNCATE TABLE {$this->table()}" ); // phpcs:ignore
	}

	/** @return array<string, int> */
	public function counts(): array {
		global $wpdb;
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$this->table()} GROUP BY status" ) ?: []; // phpcs:ignore
		$base  = [ 'pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0, 'held' => 0 ];
		foreach ( $rows as $row ) {
			$base[ $row->status ] = (int) $row->cnt;
		}
		return $base;
	}
}
