<?php
declare( strict_types=1 );

namespace WP_Route_Manager\DB;

defined( 'ABSPATH' ) || exit;

final class LogRepository {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wprm_logs';
	}

	/**
	 * @param array{
	 *   endpoint_id?: int,
	 *   endpoint_slug?: string,
	 *   key_id?: int,
	 *   method?: string,
	 *   caller_ip?: string,
	 *   request_headers?: array<string,mixed>,
	 *   request_params?: array<string,mixed>,
	 *   request_body_raw?: string,
	 *   request_body_parsed?: array<string,mixed>,
	 *   response_code?: int,
	 *   response_body?: string,
	 *   duration_ms?: int,
	 *   snippet_output?: string,
	 *   error?: string,
	 * } $data
	 */
	public function record( array $data ): int {
		global $wpdb;

		$row = [
			'endpoint_id'         => $data['endpoint_id'] ?? null,
			'endpoint_slug'       => $data['endpoint_slug'] ?? '',
			'key_id'              => $data['key_id'] ?? null,
			'method'              => strtoupper( $data['method'] ?? 'POST' ),
			'caller_ip'           => $data['caller_ip'] ?? '',
			'request_headers'     => wp_json_encode( $data['request_headers'] ?? [] ),
			'request_params'      => wp_json_encode( $data['request_params'] ?? [] ),
			'request_body_raw'    => $this->truncate( $data['request_body_raw'] ?? '' ),
			'request_body_parsed' => wp_json_encode( $data['request_body_parsed'] ?? [] ),
			'response_code'       => $data['response_code'] ?? 200,
			'response_body'       => $this->truncate( $data['response_body'] ?? '' ),
			'duration_ms'         => $data['duration_ms'] ?? 0,
			'snippet_output'      => $this->truncate( $data['snippet_output'] ?? '' ),
			'error'               => $data['error'] ?? null,
			'created_at'          => current_time( 'mysql' ),
		];

		$formats = [ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' ];
		$wpdb->insert( $this->table(), $row, $formats );

		return (int) $wpdb->insert_id;
	}

	private function truncate( string $str, int $max = 65535 ): string {
		return mb_substr( $str, 0, $max );
	}

	/**
	 * @param array{
	 *   endpoint_id?: int,
	 *   snippet_id?: int,
	 *   key_id?: int,
	 *   response_code?: int,
	 *   date_from?: string,
	 *   date_to?: string,
	 *   search?: string,
	 *   limit?: int,
	 *   offset?: int,
	 *   orderby?: string,
	 *   order?: string,
	 * } $args
	 * @return array{items: object[], total: int}
	 */
	public function query( array $args = [] ): array {
		global $wpdb;
		$table = $this->table();

		$where   = [ '1=1' ];
		$prepare = [];

		if ( ! empty( $args['endpoint_id'] ) ) {
			$where[]   = 'endpoint_id = %d';
			$prepare[] = (int) $args['endpoint_id'];
		}
		if ( ! empty( $args['key_id'] ) ) {
			$where[]   = 'key_id = %d';
			$prepare[] = (int) $args['key_id'];
		}
		if ( ! empty( $args['response_code'] ) ) {
			$where[]   = 'response_code = %d';
			$prepare[] = (int) $args['response_code'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]   = 'created_at >= %s';
			$prepare[] = sanitize_text_field( $args['date_from'] );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]   = 'created_at <= %s';
			$prepare[] = sanitize_text_field( $args['date_to'] );
		}

		$where_sql = implode( ' AND ', $where );
		$order     = strtoupper( $args['order'] ?? 'DESC' );
		$order     = in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'DESC';
		$limit     = max( 1, min( 500, (int) ( $args['limit'] ?? 25 ) ) );
		$offset    = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$data_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at {$order} LIMIT %d OFFSET %d";

		$prepare_count = $prepare;
		$prepare_data  = array_merge( $prepare, [ $limit, $offset ] );

		if ( $prepare_count ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $prepare_count ) ); // phpcs:ignore
			$items = $wpdb->get_results( $wpdb->prepare( $data_sql, $prepare_data ) ) ?: []; // phpcs:ignore
		} else {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore
			$items = $wpdb->get_results( $wpdb->prepare( $data_sql, [ $limit, $offset ] ) ) ?: []; // phpcs:ignore
		}

		return [ 'items' => $items, 'total' => $total ];
	}

	public function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table()} WHERE id = %d",
			$id
		) ) ?: null;
	}

	public function purge_old( int $days ): int {
		global $wpdb;
		if ( $days <= 0 ) {
			return 0;
		}
		return (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$this->table()} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );
	}

	public function clear_all(): int {
		global $wpdb;
		return (int) $wpdb->query( "TRUNCATE TABLE {$this->table()}" ); // phpcs:ignore
	}

	/** Tail — last N entries. */
	public function tail( int $n = 10 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d",
			$n
		) ) ?: [];
	}
}
