<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Actions;

use WP_REST_Request;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class PhpSnippetHandler extends AbstractHandler {

	public function handle( int $endpoint_id, array $data, WP_REST_Request $request ): mixed {
		if ( ! WPRM_ALLOW_PHP_SNIPPETS ) {
			return new WP_Error(
				'wprm_snippets_disabled',
				__( 'PHP snippet execution is disabled. Add define(\'WPRM_ALLOW_PHP_SNIPPETS\', true) to wp-config.php to enable.', 'wp-route-manager' ),
				[ 'status' => 403 ]
			);
		}

		$snippet_id = (int) get_post_meta( $endpoint_id, 'wprm_action_snippet_id', true );

		if ( ! $snippet_id ) {
			return new WP_Error( 'wprm_no_snippet', __( 'No snippet assigned to this endpoint.', 'wp-route-manager' ), [ 'status' => 500 ] );
		}

		$snippet_post = get_post( $snippet_id );
		if ( ! $snippet_post || $snippet_post->post_status !== 'publish' ) {
			return new WP_Error( 'wprm_snippet_inactive', __( 'The assigned snippet is inactive or not found.', 'wp-route-manager' ), [ 'status' => 500 ] );
		}

		$code           = (string) get_post_meta( $snippet_id, 'wprm_snippet_code', true );
		$catch_errors   = (bool) get_post_meta( $snippet_id, 'wprm_snippet_catch_errors', true );
		$timeout        = (int) get_post_meta( $snippet_id, 'wprm_snippet_timeout', true ) ?: 10;

		if ( empty( $code ) ) {
			return new WP_Error( 'wprm_empty_snippet', __( 'Snippet code is empty.', 'wp-route-manager' ), [ 'status' => 500 ] );
		}

		// Set time limit for this snippet.
		$prev_limit = ini_get( 'max_execution_time' );
		set_time_limit( $timeout );

		ob_start();
		$result    = null;
		$error_msg = null;

		if ( $catch_errors ) {
			// Install a custom error handler to catch E_WARNING, E_NOTICE etc.
			set_error_handler( function ( int $errno, string $errstr, string $errfile, int $errline ) use ( &$error_msg ): bool {
				$error_msg .= sprintf( "[PHP %d] %s in %s:%d\n", $errno, $errstr, basename( $errfile ), $errline );
				return true; // Don't bubble to default handler.
			} );

			try {
				$result = $this->run( $code, $data, $request, $endpoint_id );
			} catch ( \Throwable $e ) {
				$error_msg .= sprintf( "[Exception] %s in %s:%d\n", $e->getMessage(), basename( $e->getFile() ), $e->getLine() );
				$result     = new WP_Error(
					'wprm_snippet_exception',
					$e->getMessage(),
					[ 'status' => 500, 'trace' => $e->getTraceAsString() ]
				);
			} finally {
				restore_error_handler();
			}
		} else {
			$result = $this->run( $code, $data, $request, $endpoint_id );
		}

		$printed_output = (string) ob_get_clean();

		// Restore time limit.
		set_time_limit( (int) $prev_limit );

		// Build debug output for the log.
		$debug_lines = [];
		if ( $printed_output ) {
			$debug_lines[] = '=== print/echo output ===';
			$debug_lines[] = $printed_output;
		}
		if ( $error_msg ) {
			$debug_lines[] = '=== PHP errors ===';
			$debug_lines[] = $error_msg;
		}
		$debug_lines[] = '=== return value type: ' . ( is_wp_error( $result ) ? 'WP_Error' : gettype( $result ) ) . ' ===';

		$this->output = implode( "\n", $debug_lines );

		// Store this snippet's run in WP debug log if WP_DEBUG_LOG is on.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && $error_msg ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP Route Manager] Snippet #' . $snippet_id . ' errors: ' . $error_msg );
		}

		// Fire action for external listeners (e.g. logging to external service).
		do_action( 'wprm_snippet_executed', $snippet_id, $endpoint_id, $result, $this->output );

		return $result;
	}

	/**
	 * Execute snippet code in an isolated scope.
	 * Variables available inside snippet: $data, $request, $endpoint, $wpdb, $wp_query.
	 */
	private function run(
		string $code,
		array $data,
		WP_REST_Request $request,
		int $endpoint
	): mixed {
		global $wpdb, $wp_query;

		// We use a static closure so $this is not accidentally available in snippet scope.
		return ( static function (
			string $__code,
			array $data,
			WP_REST_Request $request,
			int $endpoint,
			\wpdb $wpdb,
			mixed $wp_query
		): mixed {
			return eval( '?>' . $__code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Intentional; gated by constant + capability.
		} )( $code, $data, $request, $endpoint, $wpdb, $wp_query );
	}
}
