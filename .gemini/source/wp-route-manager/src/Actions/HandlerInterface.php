<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Actions;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * All action handlers must implement this interface.
 *
 * handle() performs the action and returns the result (array, WP_Error, or null).
 * get_output() returns any captured debug output (ob_start / error messages) for logging.
 */
interface HandlerInterface {
	/**
	 * @param int             $endpoint_id  Endpoint post ID.
	 * @param array<string,mixed> $data     Parsed request body.
	 * @param WP_REST_Request $request      The full REST request.
	 * @return mixed                        Response data, WP_Error, or null.
	 */
	public function handle( int $endpoint_id, array $data, WP_REST_Request $request ): mixed;

	/** Returns captured output / debug info generated during handle(). */
	public function get_output(): string;
}
