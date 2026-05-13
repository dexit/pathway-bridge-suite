<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Actions;

use WP_REST_Request;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Dispatcher {

	private string $last_output = '';

	public function dispatch( int $endpoint_id, array $data, WP_REST_Request $request ): mixed {
		$action_type = (string) get_post_meta( $endpoint_id, 'wprm_action_type', true );

		$handler = match ( $action_type ) {
			'wp_filter'     => new WpFilterHandler(),
			'http_forward'  => new HttpForwardHandler(),
			'php_snippet'   => new PhpSnippetHandler(),
			'dispatch'      => new DispatchHandler(),
			'store_payload' => new StorePayloadHandler(),
			default         => new WpActionHandler(),
		};

		// Apply extension filter — allows replacing/wrapping handlers.
		$handler = apply_filters( 'wprm_action_handler', $handler, $action_type, $endpoint_id );

		$result = $handler->handle( $endpoint_id, $data, $request );
		$this->last_output = $handler->get_output();

		return $result;
	}

	public function get_last_output(): string {
		return $this->last_output;
	}
}
