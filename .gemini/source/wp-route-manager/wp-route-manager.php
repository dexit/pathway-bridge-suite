<?php
/**
 * Plugin Name:       WP Route Manager
 * Plugin URI:        https://github.com/pathway/wp-route-manager
 * Description:       Custom REST API endpoint manager, webhook ingestion, PHP snippet runner and action dispatcher for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Pathway Group
 * Author URI:        https://pathwayskillszone.ac.uk
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-route-manager
 * Domain Path:       /languages
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// ── Constants ────────────────────────────────────────────────────────────────
define( 'WPRM_VERSION',   '1.0.0' );
define( 'WPRM_FILE',      __FILE__ );
define( 'WPRM_DIR',       plugin_dir_path( __FILE__ ) );
define( 'WPRM_URL',       plugin_dir_url( __FILE__ ) );
define( 'WPRM_BASENAME',  plugin_basename( __FILE__ ) );

/**
 * Allow PHP snippet execution.
 * Must be explicitly opted-in via wp-config.php:
 *   define( 'WPRM_ALLOW_PHP_SNIPPETS', true );
 */
if ( ! defined( 'WPRM_ALLOW_PHP_SNIPPETS' ) ) {
	define( 'WPRM_ALLOW_PHP_SNIPPETS', false );
}

// ── Autoloader ───────────────────────────────────────────────────────────────
spl_autoload_register( function ( string $class ): void {
	$prefix = 'WP_Route_Manager\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative = substr( $class, $len );
	$file     = WPRM_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// ── Activation / Deactivation / Uninstall ────────────────────────────────────
register_activation_hook( __FILE__, function (): void {
	( new WP_Route_Manager\DB\Schema() )->install();
	( new WP_Route_Manager\PostTypes\EndpointPostType() )->register();
	( new WP_Route_Manager\PostTypes\SnippetPostType() )->register();
	flush_rewrite_rules();

	// Load demo data on first activation only.
	if ( ! get_option( 'wprm_demo_loaded' ) ) {
		( new WP_Route_Manager\Demo\DemoLoader() )->load();
		update_option( 'wprm_demo_loaded', WPRM_VERSION );
	}
} );

register_deactivation_hook( __FILE__, function (): void {
	wp_clear_scheduled_hook( 'wprm_log_purge' );
	flush_rewrite_rules();
} );

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function (): void {
	// Dependency check runs first — it will show notices and bail if anything
	// critical is missing. Plugin::init() only runs when all deps are satisfied.
	$deps = new WP_Route_Manager\Dependencies\DependencyManager();
	if ( ! $deps->all_met() ) {
		$deps->init_notices();
		return;
	}

	( new WP_Route_Manager\Plugin() )->init();
} );
