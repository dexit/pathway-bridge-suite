<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Cli;

use WP_Route_Manager\DB\ApiKeyRepository;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage WP Route Manager API keys.
 *
 * ## EXAMPLES
 *
 *   wp wprm key list
 *   wp wprm key create --label="CI Server"
 *   wp wprm key revoke 3 --yes
 *   wp wprm key toggle 3
 *
 * @package WP_Route_Manager
 */
class KeyCommand {

	private ApiKeyRepository $repo;

	public function __construct() {
		$this->repo = new ApiKeyRepository();
	}

	/**
	 * List all API keys.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm key list
	 *   wp wprm key list --format=json
	 *
	 * @subcommand list
	 */
	public function list_cmd( array $args, array $assoc ): void {
		$format = $assoc['format'] ?? 'table';
		$keys   = $this->repo->all();
		$rows   = array_map( fn( $k ) => [
			'id'        => $k->id,
			'label'     => $k->label,
			'prefix'    => $k->key_prefix . '…',
			'status'    => ( $k->status === '1' || $k->status === 1 ) ? 'active' : 'inactive',
			'last_used' => $k->last_used_at ?: 'never',
			'created'   => $k->created_at,
			'endpoints' => $k->allowed_endpoints
				? implode( ',', (array) json_decode( $k->allowed_endpoints ) )
				: 'all',
		], $keys );

		WP_CLI\Utils\format_items( $format, $rows, [ 'id', 'label', 'prefix', 'status', 'endpoints', 'last_used', 'created' ] );
	}

	/**
	 * Create a new API key. The plain key is shown once — copy it immediately.
	 *
	 * ## OPTIONS
	 *
	 * --label=<label>
	 * : A human-readable label for the key.
	 *
	 * [--endpoints=<ids>]
	 * : Comma-separated endpoint post IDs this key can access. Omit for all.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm key create --label="Zapier Integration"
	 *   wp wprm key create --label="CI Server" --endpoints=1,3,5
	 */
	public function create( array $args, array $assoc ): void {
		$label = $assoc['label'] ?? '';
		if ( empty( $label ) ) {
			WP_CLI::error( '--label is required.' );
		}

		$ep_ids = [];
		if ( ! empty( $assoc['endpoints'] ) ) {
			$ep_ids = array_map( 'absint', explode( ',', $assoc['endpoints'] ) );
			$ep_ids = array_filter( $ep_ids );
		}

		$result = $this->repo->create( sanitize_text_field( $label ), $ep_ids );

		WP_CLI::success( sprintf( 'API key created (ID: %d).', $result['id'] ) );
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%YIMPORTANT: Copy this key now — it will NOT be shown again:%n' ) );
		WP_CLI::line( '' );
		WP_CLI::line( '  ' . $result['plain_key'] );
		WP_CLI::line( '' );
	}

	/**
	 * Revoke (permanently delete) an API key.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Key ID.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm key revoke 3 --yes
	 */
	public function revoke( array $args, array $assoc ): void {
		$id  = absint( $args[0] ?? 0 );
		$key = $this->repo->get( $id );
		if ( ! $key ) {
			WP_CLI::error( "Key #{$id} not found." );
		}
		WP_CLI::confirm( sprintf( 'Delete key #%d (%s)? This cannot be undone.', $id, $key->label ), $assoc );
		$this->repo->delete( $id );
		WP_CLI::success( "Key #{$id} revoked." );
	}

	/**
	 * Toggle a key between active and inactive.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Key ID.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm key toggle 3
	 */
	public function toggle( array $args, array $assoc ): void {
		$id = absint( $args[0] ?? 0 );
		if ( ! $this->repo->get( $id ) ) {
			WP_CLI::error( "Key #{$id} not found." );
		}
		$this->repo->toggle_status( $id );
		$key = $this->repo->get( $id );
		WP_CLI::success( sprintf( 'Key #%d is now %s.', $id, $key && ( $key->status === '1' || $key->status === 1 ) ? 'active' : 'inactive' ) );
	}
}
