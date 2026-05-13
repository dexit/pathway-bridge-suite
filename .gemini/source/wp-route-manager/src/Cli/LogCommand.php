<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Cli;

use WP_Route_Manager\DB\LogRepository;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * View and manage WP Route Manager request logs.
 *
 * ## EXAMPLES
 *
 *   wp wprm log list
 *   wp wprm log list --endpoint=5 --limit=10 --errors-only
 *   wp wprm log tail --watch
 *   wp wprm log clear --yes
 *   wp wprm log clear --before=2026-01-01 --yes
 *
 * @package WP_Route_Manager
 */
class LogCommand {

	private LogRepository $logs;

	public function __construct() {
		$this->logs = new LogRepository();
	}

	/**
	 * List log entries with optional filters.
	 *
	 * ## OPTIONS
	 *
	 * [--endpoint=<id>]
	 * : Filter by endpoint post ID.
	 *
	 * [--limit=<n>]
	 * : Number of entries to show. Default: 25.
	 *
	 * [--offset=<n>]
	 * : Pagination offset. Default: 0.
	 *
	 * [--format=<format>]
	 * : Output format: table, json, csv. Default: table.
	 *
	 * [--errors-only]
	 * : Only show entries with 4xx/5xx response codes.
	 *
	 * [--code=<code>]
	 * : Filter by exact HTTP response code.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm log list
	 *   wp wprm log list --endpoint=5 --limit=50 --format=json
	 *   wp wprm log list --errors-only
	 *   wp wprm log list --code=401
	 *
	 * @subcommand list
	 */
	public function list_cmd( array $args, array $assoc ): void {
		$query_args = [
			'limit'         => absint( $assoc['limit'] ?? 25 ),
			'offset'        => absint( $assoc['offset'] ?? 0 ),
			'endpoint_id'   => absint( $assoc['endpoint'] ?? 0 ) ?: null,
			'response_code' => absint( $assoc['code'] ?? 0 ) ?: null,
		];

		$result = $this->logs->query( array_filter( $query_args, fn( $v ) => $v !== null ) );
		$items  = $result['items'];

		if ( isset( $assoc['errors-only'] ) ) {
			$items = array_values( array_filter( $items, fn( $i ) => (int) $i->response_code >= 400 ) );
		}

		$rows = array_map( fn( $i ) => [
			'id'       => $i->id,
			'endpoint' => $i->endpoint_slug,
			'method'   => $i->method,
			'ip'       => $i->caller_ip,
			'code'     => $i->response_code,
			'ms'       => $i->duration_ms . 'ms',
			'time'     => $i->created_at,
			'error'    => $i->error ?: '',
		], $items );

		$format = $assoc['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $rows, [ 'id', 'endpoint', 'method', 'ip', 'code', 'ms', 'time', 'error' ] );
		WP_CLI::line( '' );
		WP_CLI::line( sprintf( 'Showing %d of %d total.', count( $rows ), $result['total'] ) );
	}

	/**
	 * Show the full detail of a single log entry.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Log entry ID.
	 *
	 * [--format=<format>]
	 * : table or json. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm log get 42
	 *   wp wprm log get 42 --format=json
	 */
	public function get( array $args, array $assoc ): void {
		$id  = absint( $args[0] ?? 0 );
		$log = $this->logs->get( $id );

		if ( ! $log ) {
			WP_CLI::error( "Log entry #{$id} not found." );
		}

		$format = $assoc['format'] ?? 'table';

		if ( $format === 'json' ) {
			WP_CLI::line( wp_json_encode( $log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		$fields = [
			'id'                  => $log->id,
			'endpoint_slug'       => $log->endpoint_slug,
			'method'              => $log->method,
			'caller_ip'           => $log->caller_ip,
			'response_code'       => $log->response_code,
			'duration_ms'         => $log->duration_ms . 'ms',
			'created_at'          => $log->created_at,
			'error'               => $log->error ?: '—',
		];

		foreach ( $fields as $k => $v ) {
			WP_CLI::line( sprintf( '%-22s %s', $k . ':', $v ) );
		}

		WP_CLI::line( '' );
		WP_CLI::line( '── Request Headers ──' );
		WP_CLI::line( $this->pretty_json( $log->request_headers ) );

		WP_CLI::line( '' );
		WP_CLI::line( '── Request Params ──' );
		WP_CLI::line( $this->pretty_json( $log->request_params ) );

		WP_CLI::line( '' );
		WP_CLI::line( '── Parsed Body ──' );
		WP_CLI::line( $this->pretty_json( $log->request_body_parsed ) );

		WP_CLI::line( '' );
		WP_CLI::line( '── Response ──' );
		WP_CLI::line( $this->pretty_json( $log->response_body ) );

		if ( $log->snippet_output ) {
			WP_CLI::line( '' );
			WP_CLI::line( '── Snippet Output ──' );
			WP_CLI::line( $log->snippet_output );
		}
	}

	/**
	 * Tail the most recent log entries. Optionally watch for new entries.
	 *
	 * ## OPTIONS
	 *
	 * [--n=<n>]
	 * : Number of recent entries to show. Default: 10.
	 *
	 * [--watch]
	 * : Poll every 3 seconds and print new entries as they arrive.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm log tail
	 *   wp wprm log tail --n=20
	 *   wp wprm log tail --watch
	 */
	public function tail( array $args, array $assoc ): void {
		$n     = absint( $assoc['n'] ?? 10 );
		$watch = isset( $assoc['watch'] );

		if ( $watch ) {
			WP_CLI::line( WP_CLI::colorize( '%CWatching for new log entries (Ctrl+C to stop)…%n' ) );
			WP_CLI::line( '' );
		}

		$last_id = 0;

		do {
			$items = $this->logs->tail( $watch ? 50 : $n );
			$new   = $watch
				? array_filter( $items, fn( $i ) => (int) $i->id > $last_id )
				: $items;

			foreach ( array_reverse( array_values( $new ) ) as $i ) {
				$last_id = max( $last_id, (int) $i->id );

				$code     = (int) $i->response_code;
				$code_str = $code >= 400
					? WP_CLI::colorize( '%R' . $code . '%n' )
					: WP_CLI::colorize( '%G' . $code . '%n' );

				$err = $i->error ? WP_CLI::colorize( ' %r' . $i->error . '%n' ) : '';

				WP_CLI::line( sprintf(
					'[%s] #%-5d  %-6s  %-40s  %s  %dms%s',
					$i->created_at,
					$i->id,
					$i->method,
					$i->endpoint_slug,
					$code_str,
					$i->duration_ms,
					$err
				) );
			}

			if ( $watch ) {
				sleep( 3 );
			}
		} while ( $watch );
	}

	/**
	 * Clear log entries. Supports clearing all, by endpoint, or by date.
	 *
	 * ## OPTIONS
	 *
	 * [--endpoint=<id>]
	 * : Only delete logs for a specific endpoint post ID.
	 *
	 * [--before=<date>]
	 * : Only delete logs created before this date (YYYY-MM-DD).
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm log clear --yes
	 *   wp wprm log clear --endpoint=5 --yes
	 *   wp wprm log clear --before=2026-01-01 --yes
	 */
	public function clear( array $args, array $assoc ): void {
		$endpoint_id = absint( $assoc['endpoint'] ?? 0 );
		$before      = sanitize_text_field( $assoc['before'] ?? '' );

		WP_CLI::confirm( 'Delete log entries? This cannot be undone.', $assoc );

		if ( $endpoint_id ) {
			global $wpdb;
			$deleted = (int) $wpdb->delete(
				$wpdb->prefix . 'wprm_logs',
				[ 'endpoint_id' => $endpoint_id ],
				[ '%d' ]
			);
			WP_CLI::success( sprintf( 'Deleted %d log entries for endpoint #%d.', $deleted, $endpoint_id ) );
			return;
		}

		if ( $before ) {
			$days = (int) round( ( time() - (int) strtotime( $before ) ) / DAY_IN_SECONDS );
			if ( $days > 0 ) {
				$deleted = $this->logs->purge_old( $days );
				WP_CLI::success( sprintf( 'Deleted %d entries older than %s.', $deleted, $before ) );
				return;
			}
			WP_CLI::error( 'The --before date must be in the past.' );
		}

		$this->logs->clear_all();
		WP_CLI::success( 'All log entries deleted.' );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function pretty_json( ?string $json ): string {
		if ( ! $json ) {
			return '—';
		}
		$decoded = json_decode( $json, true );
		if ( $decoded === null ) {
			return $json;
		}
		return (string) wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
