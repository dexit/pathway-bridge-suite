<?php
/**
 * Module Registry
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE;

use WPCT_PLUGIN\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Registry class to manage suite modules.
 */
class Registry extends Singleton {

	/**
	 * Holds the registered modules.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Register a module instance.
	 *
	 * @param string $name Module name/slug.
	 * @param mixed  $instance Module instance.
	 */
	public function register( $name, $instance ) {
		$this->modules[ $name ] = $instance;
	}

	/**
	 * Get a module instance.
	 *
	 * @param string $name Module name.
	 *
	 * @return mixed|null
	 */
	public function get( $name ) {
		return $this->modules[ $name ] ?? null;
	}

	/**
	 * Get all registered modules.
	 *
	 * @return array
	 */
	public function get_modules() {
		return $this->modules;
	}
}
