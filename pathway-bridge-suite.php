<?php
/**
 * Plugin Name:         Pathway Bridge Suite
 * Plugin URI:          https://github.com/pathway/bridge-suite
 * Description:         A unified connectivity hub for WordPress: Forms, Posts, and Custom REST Routes with Elementor integration.
 * Author:              codeccoop
 * Author URI:          https://www.codeccoop.org
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         pathway-bridge-suite
 * Domain Path:         /languages
 * Version:             1.0.0
 * Requires PHP:        8.0
 * Requires at least:   6.7
 *
 * @package pathwaybridgesuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

define( 'PATHWAY_BRIDGE_SUITE_INDEX', __FILE__ );
define( 'PATHWAY_BRIDGE_SUITE_DIR', __DIR__ );
define( 'PATHWAY_BRIDGE_SUITE_VERSION', '1.0.0' );

/* Packages */
if ( ! class_exists( 'WPCT_PLUGIN\Plugin' ) ) {
	require_once __DIR__ . '/deps/plugin/class-plugin.php';
}

if ( ! class_exists( 'HTTP_BRIDGE\Backend' ) ) {
	require_once __DIR__ . '/deps/http/index.php';
}

/**
 * Main Suite Class
 */
class Pathway_Bridge_Suite extends \WPCT_PLUGIN\Plugin {

	protected static $name = 'Pathway Bridge Suite';
	protected static $textdomain = 'pathway-bridge-suite';

	public function init() {
		parent::init();

		// Load Modules
		$this->load_modules();
	}

	private function load_modules() {
		// Core Modules
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/class-registry.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/class-workflow-engine.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/class-logger.php';

		// Bridge Modules
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/modules/forms/class-forms-module.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/modules/posts/class-posts-module.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/modules/routes/class-routes-module.php';
	}

	public static function activate() {
		// Activation logic (Migrated from both plugins)
	}

	public static function deactivate() {
		// Deactivation logic
	}
}

add_action( 'plugins_loaded', function () {
	Pathway_Bridge_Suite::get_instance();
}, 10 );

// Load Workflow Jobs
require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-http-job.php';
require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-rest-job.php';
require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-webhook-job.php';
require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-transformer.php';
require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/class-rate-limiter.php';
require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-queue.php';
