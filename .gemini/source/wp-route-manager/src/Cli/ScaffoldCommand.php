<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Cli;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Scaffold PHP code stubs for WP Route Manager integration.
 *
 * ## EXAMPLES
 *
 *   wp wprm scaffold handler my_order_ingest
 *   wp wprm scaffold handler my_transform --type=filter
 *   wp wprm scaffold snippet "Process Order"
 *   wp wprm scaffold extension my-extension
 *
 * @package WP_Route_Manager
 */
class ScaffoldCommand {

	/**
	 * Generate a ready-to-paste PHP handler stub for a WP action or filter hook.
	 *
	 * ## OPTIONS
	 *
	 * <hook-name>
	 * : The WordPress hook name to scaffold for. E.g. "my_order_ingest".
	 *
	 * [--type=<type>]
	 * : action or filter. Default: action.
	 *
	 * [--output=<path>]
	 * : Write to a file instead of stdout.
	 *
	 * ## EXAMPLES
	 *
	 *   # Scaffold an action handler (fires on do_action)
	 *   wp wprm scaffold handler my_order_ingest
	 *
	 *   # Scaffold a filter handler (fires on apply_filters, must return response)
	 *   wp wprm scaffold handler my_transform --type=filter
	 *
	 *   # Write directly to a file
	 *   wp wprm scaffold handler my_order_ingest --output=./my-handler.php
	 */
	public function handler( array $args, array $assoc ): void {
		$hook = sanitize_key( $args[0] ?? '' );
		if ( ! $hook ) {
			WP_CLI::error( 'Provide a hook name.' );
		}

		$type = $assoc['type'] ?? 'action';

		if ( $type === 'filter' ) {
			$code = $this->filter_stub( $hook );
		} else {
			$code = $this->action_stub( $hook );
		}

		$output_path = $assoc['output'] ?? '';

		if ( $output_path ) {
			file_put_contents( $output_path, $code );
			WP_CLI::success( "Stub written to {$output_path}" );
		} else {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%GPaste this in your theme\'s functions.php or a custom plugin:%n' ) );
			WP_CLI::line( '' );
			WP_CLI::line( $code );
		}
	}

	/**
	 * Scaffold a PHP snippet post directly in the database (creates a draft snippet CPT).
	 *
	 * ## OPTIONS
	 *
	 * <title>
	 * : Human-readable title for the snippet.
	 *
	 * [--type=<type>]
	 * : Snippet type: action_handler, filter_handler, body_parser, http_transform, standalone.
	 *   Default: standalone.
	 *
	 * [--activate]
	 * : Publish the snippet immediately instead of saving as draft.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm scaffold snippet "Process Incoming Order" --type=filter_handler
	 *   wp wprm scaffold snippet "Custom Body Parser" --type=body_parser --activate
	 */
	public function snippet( array $args, array $assoc ): void {
		$title = $args[0] ?? '';
		if ( ! $title ) {
			WP_CLI::error( 'Provide a title.' );
		}

		$type   = $assoc['type'] ?? 'standalone';
		$status = isset( $assoc['activate'] ) ? 'publish' : 'draft';

		$stubs = [
			'action_handler'  => $this->snippet_action_stub(),
			'filter_handler'  => $this->snippet_filter_stub(),
			'body_parser'     => $this->snippet_parser_stub(),
			'http_transform'  => $this->snippet_transform_stub(),
			'standalone'      => $this->snippet_standalone_stub(),
		];

		$code = $stubs[ $type ] ?? $stubs['standalone'];

		$post_id = wp_insert_post( [
			'post_title'  => sanitize_text_field( $title ),
			'post_type'   => 'wprm_snippet',
			'post_status' => $status,
		], true );

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::error( $post_id->get_error_message() );
		}

		update_post_meta( $post_id, 'wprm_snippet_type',         $type );
		update_post_meta( $post_id, 'wprm_snippet_code',         $code );
		update_post_meta( $post_id, 'wprm_snippet_catch_errors', 1 );
		update_post_meta( $post_id, 'wprm_snippet_timeout',      10 );

		$edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		WP_CLI::success( sprintf( 'Snippet #%d "%s" created (%s).', $post_id, $title, $status ) );
		WP_CLI::line( '  Edit → ' . $edit_url );
	}

	/**
	 * Scaffold an extension plugin stub that demonstrates all WPRM hooks.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Plugin folder/file slug. E.g. "my-wprm-extension".
	 *
	 * [--dir=<path>]
	 * : Output directory. Default: current working directory.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wprm scaffold extension my-wprm-extension
	 *   wp wprm scaffold extension my-wprm-extension --dir=/var/www/html/wp-content/plugins/
	 */
	public function extension( array $args, array $assoc ): void {
		$slug = sanitize_title( $args[0] ?? '' );
		if ( ! $slug ) {
			WP_CLI::error( 'Provide a plugin slug.' );
		}

		$dir = rtrim( $assoc['dir'] ?? getcwd(), '/' ) . '/' . $slug;

		if ( ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			WP_CLI::error( "Could not create directory: {$dir}" );
		}

		$class   = str_replace( '-', '_', ucwords( $slug, '-' ) );
		$content = $this->extension_stub( $slug, $class );

		file_put_contents( $dir . '/' . $slug . '.php', $content );

		WP_CLI::success( "Extension stub created:" );
		WP_CLI::line( '  ' . $dir . '/' . $slug . '.php' );
		WP_CLI::line( '' );
		WP_CLI::line( 'Activate with: wp plugin activate ' . $slug );
	}

	// ── Code stubs ────────────────────────────────────────────────────────────

	private function action_stub( string $hook ): string {
		return <<<PHP
<?php
/**
 * WP Route Manager — Action Handler: {$hook}
 *
 * Add this to functions.php or a custom plugin.
 * This function fires every time a request hits an endpoint
 * configured with action hook "{$hook}".
 *
 * @param array           \$data       Parsed request body (key => value pairs).
 * @param WP_REST_Request \$request    The full REST request object.
 * @param int             \$endpoint   Endpoint post ID.
 */
add_action( '{$hook}', function ( array \$data, \WP_REST_Request \$request, int \$endpoint ): void {

    // Example: log incoming data.
    error_log( 'Received via WPRM: ' . print_r( \$data, true ) );

    // Example: validate required fields.
    \$email = sanitize_email( \$data['email'] ?? '' );
    if ( ! is_email( \$email ) ) {
        // You cannot return a value from an action, but you can log the error.
        error_log( 'WPRM {$hook}: invalid email received.' );
        return;
    }

    // Example: save to a custom table, update a user, trigger another hook, etc.
    do_action( 'my_plugin_process_data', \$data );

}, 10, 3 );
PHP;
	}

	private function filter_stub( string $hook ): string {
		return <<<PHP
<?php
/**
 * WP Route Manager — Filter Handler: {$hook}
 *
 * Add this to functions.php or a custom plugin.
 * This function fires when an endpoint uses apply_filters("{$hook}", \$data).
 * Whatever you return is sent back to the caller as the JSON response.
 *
 * @param mixed           \$data       Parsed request body (usually array).
 * @param WP_REST_Request \$request    The full REST request object.
 * @param int             \$endpoint   Endpoint post ID.
 * @return array|WP_Error             Returned as the HTTP response.
 */
add_filter( '{$hook}', function ( mixed \$data, \WP_REST_Request \$request, int \$endpoint ): array|\WP_Error {

    // Validate.
    if ( empty( \$data['email'] ) ) {
        return new \WP_Error( 'missing_email', 'Email is required.', [ 'status' => 422 ] );
    }

    // Transform / enrich.
    return [
        'status'     => 'ok',
        'received'   => \$data,
        'processed'  => true,
        'site'       => get_bloginfo( 'name' ),
        'timestamp'  => current_time( 'c' ),
    ];

}, 10, 3 );
PHP;
	}

	private function snippet_action_stub(): string {
		return '<?php
// Action Handler Snippet
// Available: $data (array), $request (WP_REST_Request), $endpoint (int)
// Return value is ignored for action handlers.

// Validate required fields.
$email = sanitize_email( $data[\'email\'] ?? \'\' );
if ( ! is_email( $email ) ) {
    error_log( \'[WPRM] Invalid email: \' . $email );
    return;
}

// Example: create or update a WP user.
$user = get_user_by( \'email\', $email );
if ( ! $user ) {
    wp_insert_user( [
        \'user_email\' => $email,
        \'user_login\' => $email,
        \'first_name\'  => sanitize_text_field( $data[\'first_name\'] ?? \'\' ),
        \'role\'        => \'subscriber\',
    ] );
}

// Fire a downstream action.
do_action( \'my_plugin_lead_received\', $data );
';
	}

	private function snippet_filter_stub(): string {
		return '<?php
// Filter Handler Snippet
// Available: $data (array), $request (WP_REST_Request), $endpoint (int)
// MUST return an array or WP_Error.

// Validate.
if ( empty( $data[\'name\'] ) ) {
    return new WP_Error( \'missing_name\', \'Name is required.\', [ \'status\' => 422 ] );
}

// Enrich the response.
return [
    \'status\'    => \'ok\',
    \'received\'  => $data,
    \'site\'      => get_bloginfo( \'name\' ),
    \'timestamp\' => current_time( \'c\' ),
    \'user_ip\'   => $request->get_header( \'X-Forwarded-For\' ) ?: ($_SERVER[\'REMOTE_ADDR\'] ?? \'\'),
];
';
	}

	private function snippet_parser_stub(): string {
		return '<?php
// Body Parser Snippet
// Available: $raw (string — the raw request body)
// MUST return an array.

// Example: parse XML body.
// Suppress errors in case of malformed XML.
$prev = libxml_use_internal_errors( true );
$xml  = simplexml_load_string( $raw );
libxml_use_internal_errors( $prev );

if ( $xml === false ) {
    // Fall back to treating as plain text.
    return [ \'body\' => $raw ];
}

// Convert SimpleXML to plain array.
return json_decode( json_encode( $xml ), true ) ?: [ \'body\' => $raw ];
';
	}

	private function snippet_transform_stub(): string {
		return '<?php
// HTTP Transform Snippet
// Available: $data (array), $request (WP_REST_Request), $endpoint (int)
// MUST return the (possibly modified) $data array.
// Runs before the data is forwarded to the external URL.

// Remap fields to match the external API\'s expected format.
return [
    \'contact\' => [
        \'email\'      => $data[\'email\'] ?? \'\',
        \'first_name\' => $data[\'first_name\'] ?? \'\',
        \'last_name\'  => $data[\'last_name\'] ?? \'\',
    ],
    \'metadata\' => [
        \'source\'     => \'wordpress\',
        \'site_url\'   => get_site_url(),
        \'received_at\' => current_time( \'c\' ),
    ],
    // Forward everything else as-is.
    \'raw\'  => $data,
];
';
	}

	private function snippet_standalone_stub(): string {
		return '<?php
// Standalone Snippet
// Available: $data (array), $request (WP_REST_Request), $endpoint (int), $wpdb
// Can return anything — returned value becomes the JSON response.
// Return null for an empty 200 OK.
// Return a WP_Error for an error response.

// Example: query a custom table and return results.
$results = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}my_table WHERE status = %s LIMIT 10",
    sanitize_text_field( $data[\'status\'] ?? \'active\' )
) );

return [
    \'count\'   => count( $results ),
    \'results\' => $results,
];
';
	}

	private function extension_stub( string $slug, string $class ): string {
		return <<<PHP
<?php
/**
 * Plugin Name:  {$class} — WPRM Extension
 * Description:  Example extension for WP Route Manager. Demonstrates all available hooks.
 * Version:      1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// ── 1. Wait for WPRM to finish loading ───────────────────────────────────────
add_action( 'wprm_loaded', function ( \WP_Route_Manager\Plugin \$plugin ): void {

    // WPRM is fully bootstrapped here.
    // Safe to register your hooks, actions, etc.
    error_log( '{$class}: WP Route Manager is loaded.' );

} );

// ── 2. Register custom Abilities via the Abilities API (WP 6.9+) ──────────────
add_action( 'wprm_register_abilities', function (): void {
    if ( ! function_exists( 'wp_register_ability' ) ) {
        return;
    }
    wp_register_ability( 'wprm/{$slug}/enabled', [
        'label'    => '{$class} Feature Active',
        'category' => 'wprm',
        'value'    => true,
        'meta'     => [ 'readonly' => true, 'show_in_rest' => true ],
    ] );
} );

// ── 3. Modify parsed request body before dispatch ─────────────────────────────
add_filter( 'wprm_parsed_data', function ( array \$data, \WP_REST_Request \$request, int \$endpoint_id ): array {

    // Example: add a server-side timestamp to every incoming payload.
    \$data['_received_at'] = current_time( 'c' );
    \$data['_endpoint_id'] = \$endpoint_id;

    return \$data;

}, 10, 3 );

// ── 4. Replace or wrap an action handler ─────────────────────────────────────
add_filter( 'wprm_action_handler', function ( object \$handler, string \$action_type, int \$endpoint_id ): object {

    // Example: wrap the default wp_action handler with logging for endpoint #5 only.
    if ( \$action_type === 'wp_action' && \$endpoint_id === 5 ) {
        return new class( \$handler ) extends \WP_Route_Manager\Actions\AbstractHandler {
            private object \$inner;
            public function __construct( object \$inner ) { \$this->inner = \$inner; }
            public function handle( int \$ep, array \$data, \WP_REST_Request \$req ): mixed {
                error_log( '[{$class}] Before dispatch for endpoint #' . \$ep );
                \$result = \$this->inner->handle( \$ep, \$data, \$req );
                error_log( '[{$class}] After dispatch, result type: ' . gettype( \$result ) );
                \$this->output = \$this->inner->get_output();
                return \$result;
            }
        };
    }

    return \$handler;

}, 10, 3 );

// ── 5. Hook into every request — before and after dispatch ───────────────────
add_action( 'wprm_before_request', function ( \WP_REST_Request \$request, int \$endpoint_id ): void {
    // Runs after auth, before parse + dispatch.
    // Example: reject requests from specific IPs.
    \$ip = \$_SERVER['REMOTE_ADDR'] ?? '';
    if ( \$ip === '192.168.1.99' ) {
        // Note: at this point the request is already authenticated.
        // To block here you'd need to throw an exception or use a custom handler.
        error_log( '[{$class}] Blocked IP attempted access: ' . \$ip );
    }
}, 10, 2 );

add_action( 'wprm_after_request', function ( \WP_REST_Response|\WP_Error \$response, \WP_REST_Request \$request, int \$endpoint_id ): void {
    // Runs after dispatch + log, before the response is sent.
    // Example: push metrics to a custom table.
    \$code = is_wp_error( \$response ) ? 500 : \$response->get_status();
    error_log( sprintf( '[{$class}] Endpoint #%d responded %d.', \$endpoint_id, \$code ) );
}, 10, 3 );

// ── 6. React to a PHP snippet executing ──────────────────────────────────────
add_action( 'wprm_snippet_executed', function ( int \$snippet_id, int \$endpoint_id, mixed \$result, string \$output ): void {
    // Fires after every PHP snippet runs (regardless of success/failure).
    // \$output contains ob_start() capture + PHP error messages.
    if ( is_wp_error( \$result ) ) {
        error_log( sprintf( '[{$class}] Snippet #%d on endpoint #%d failed: %s', \$snippet_id, \$endpoint_id, \$result->get_error_message() ) );
    }
}, 10, 4 );

// ── 7. Handle the custom WP action fired by the demo action endpoint ──────────
add_action( 'wprm_demo_ingest', function ( array \$data, \WP_REST_Request \$request, int \$endpoint_id ): void {
    // This hook fires when a request hits the "demo/action" endpoint.
    error_log( '[{$class}] Received via demo/action: ' . wp_json_encode( \$data ) );
}, 10, 3 );

// ── 8. Enrich the demo filter endpoint response ───────────────────────────────
add_filter( 'wprm_demo_transform', function ( mixed \$data, \WP_REST_Request \$request, int \$endpoint_id ): array {
    \$data = is_array( \$data ) ? \$data : [];
    \$data['{$slug}_enriched'] = true;
    \$data['{$slug}_timestamp'] = current_time( 'c' );
    return \$data;
}, 10, 3 );

// ── 9. Modify forwarded data before it leaves WordPress ──────────────────────
add_filter( 'wprm_forward_data', function ( array \$data, int \$endpoint_id, \WP_REST_Request \$request ): array {
    // Add a signature header to all forwarded requests.
    \$data['_signature'] = hash_hmac( 'sha256', wp_json_encode( \$data ), '{$slug}-secret' );
    return \$data;
}, 10, 3 );
PHP;
	}
}
