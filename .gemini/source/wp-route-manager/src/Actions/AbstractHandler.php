<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Base implementation for all action handlers.
 * Concrete handlers extend this and implement handle().
 */
abstract class AbstractHandler implements HandlerInterface {

	/** @var string Captured debug output from the last handle() call. */
	protected string $output = '';

	public function get_output(): string {
		return $this->output;
	}
}
