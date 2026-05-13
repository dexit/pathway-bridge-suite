<?php
/**
 * Plugin Name:         Pathway Bridge Suite
 * Plugin URI:          https://github.com/pathway/bridge-suite
 * Description:         Unified connectivity hub for WordPress: Forms, Posts, and Custom REST Routes with Enterprise Workflow Engine.
 * Author:              codeccoop
 * Author URI:          https://www.codeccoop.org
 * License:             GPLv2 or later
 * Text Domain:         pathway-bridge-suite
 * Version:             1.0.1
 * Requires PHP:        8.0
 * Requires at least:   6.9
 *
 * @package pathwaybridgesuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

define( 'PATHWAY_BRIDGE_SUITE_INDEX', __FILE__ );
define( 'PATHWAY_BRIDGE_SUITE_DIR', __DIR__ );
define( 'PATHWAY_BRIDGE_SUITE_VERSION', '1.0.1' );

// Load internal deps
require_once __DIR__ . '/deps/plugin/class-plugin.php';
require_once __DIR__ . '/deps/http/index.php';

// Load Jetpack Autoloader if available
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Main Suite Class
 */
class Pathway_Bridge_Suite extends \WPCT_PLUGIN\Plugin {

	protected static $name = 'Pathway Bridge Suite';
	protected static $textdomain = 'pathway-bridge-suite';

	public function init() {
		parent::init();
		$this->load_modules();
		$this->load_addons();

		Job_Manager::get_instance()->init();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	private function load_modules() {
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/class-registry.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/class-workflow-engine.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/class-logger.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/class-rate-limiter.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/class-job-manager.php';

		// Bridge Modules
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/modules/forms/class-forms-module.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/modules/posts/class-posts-module.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/modules/routes/class-routes-module.php';

		// Workflow Core
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-transformer.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-job.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-http-job.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-webhook-job.php';
require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-mail-job.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-rest-job.php';
		require_once PATHWAY_BRIDGE_SUITE_DIR . '/includes/workflow/class-queue.php';
	}

	private function load_addons() {
		$addons_dir = PATHWAY_BRIDGE_SUITE_DIR . '/includes/addons';
		if ( is_dir( $addons_dir ) ) {
			foreach ( glob( "$addons_dir/*/hooks.php" ) as $addon_hooks ) {
				require_once $addon_hooks;
			}
		}
	}

	public function enqueue_admin_assets() {
		if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'pathway-bridge' ) !== false ) {
			wp_enqueue_script( 'pathway-bridge-dashboard', plugin_dir_url( __FILE__ ) . 'assets/bundle.js', array( 'wp-element', 'wp-components', 'wp-api-fetch' ), PATHWAY_BRIDGE_SUITE_VERSION, true );
			wp_enqueue_style( 'pathway-bridge-style', plugin_dir_url( __FILE__ ) . 'assets/style.css', array( 'wp-components' ), PATHWAY_BRIDGE_SUITE_VERSION );
		}
	}
}

add_action( 'plugins_loaded', function () {
	Pathway_Bridge_Suite::get_instance();
}, 10 );
