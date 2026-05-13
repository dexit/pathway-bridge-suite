<?php
declare( strict_types=1 );

namespace WP_Route_Manager\DB;

defined( 'ABSPATH' ) || exit;

/**
 * API key CRUD. Keys are stored as wp_hash() — plain key is returned ONCE on creation.
 */
final class ApiKeyRepository {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wprm_api_keys';
	}

	/**
	 * Create a new API key.
	 *
	 * @param string   $label             Human-readable label.
	 * @param int[]    $allowed_endpoints  Post IDs; empty = all endpoints.
	 * @return array{id: int, plain_key: string}  Plain key for display — NOT stored.
	 */
	public function create( string $label, array $allowed_endpoints = [] ): array {
		global $wpdb;

		$plain_key = 'wrmk_' . wp_generate_password( 40, false );
		$hash      = wp_hash( $plain_key );
		$prefix    = substr( $plain_key, 0, 12 );

		$wpdb->insert(
			$this->table(),
			[
				'label'             => sanitize_text_field( $label ),
				'key_hash'          => $hash,
				'key_prefix'        => $prefix,
				'allowed_endpoints' => $allowed_endpoints ? wp_json_encode( $allowed_endpoints ) : null,
				'status'            => 1,
				'created_at'        => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		return [
			'id'        => (int) $wpdb->insert_id,
			'plain_key' => $plain_key,
		];
	}

	/**
	 * Validate a plain API key. Returns the key row or null.
	 *
	 * @return object|null  DB row with id, label, allowed_endpoints, status.
	 */
	public function validate( string $plain_key ): ?object {
		global $wpdb;

		$hash = wp_hash( $plain_key );
		$row  = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table()} WHERE key_hash = %s AND status = 1",
			$hash
		) );

		if ( $row ) {
			// Update last_used_at.
			$wpdb->update(
				$this->table(),
				[ 'last_used_at' => current_time( 'mysql' ) ],
				[ 'id' => (int) $row->id ],
				[ '%s' ],
				[ '%d' ]
			);
		}

		return $row ?: null;
	}

	/** @return object[] */
	public function all(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT id, label, key_prefix, allowed_endpoints, status, last_used_at, created_at FROM {$this->table()} ORDER BY id DESC" // phpcs:ignore
		) ?: [];
	}

	public function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table()} WHERE id = %d",
			$id
		) ) ?: null;
	}

	public function toggle_status( int $id ): bool {
		global $wpdb;
		$current = $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM {$this->table()} WHERE id = %d",
			$id
		) );
		if ( $current === null ) {
			return false;
		}
		return (bool) $wpdb->update(
			$this->table(),
			[ 'status' => $current === '1' ? 0 : 1 ],
			[ 'id' => $id ],
			[ '%d' ],
			[ '%d' ]
		);
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );
	}

	/**
	 * Check if a key is allowed to access a specific endpoint post ID.
	 */
	public function can_access_endpoint( object $key_row, int $endpoint_id ): bool {
		if ( empty( $key_row->allowed_endpoints ) ) {
			return true; // null = all endpoints.
		}
		$allowed = json_decode( $key_row->allowed_endpoints, true );
		return is_array( $allowed ) && in_array( $endpoint_id, $allowed, true );
	}
}
