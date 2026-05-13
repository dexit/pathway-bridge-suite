<?php
/**
 * Uninstall WP Route Manager.
 * Drops custom tables and removes all plugin options.
 *
 * Only runs when the user clicks "Delete" in WP admin.
 * register_uninstall_hook() is not used — WordPress calls this file directly.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Only load the PSR-4 autoloader — do NOT require the full plugin file
// (that would run activation hooks and plugins_loaded bootstrap).
spl_autoload_register( function ( string $class ): void {
	$prefix = 'WP_Route_Manager\\';
	$len    = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}
	$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Also need the WPRM_ALLOW_PHP_SNIPPETS constant (used by some classes).
if ( ! defined( 'WPRM_ALLOW_PHP_SNIPPETS' ) ) {
	define( 'WPRM_ALLOW_PHP_SNIPPETS', false );
}

( new WP_Route_Manager\DB\Schema() )->uninstall();

// Delete all plugin options.
$options = [
	'wprm_namespace',
	'wprm_api_version',
	'wprm_global_logging',
	'wprm_log_retention_days',
	'wprm_enable_cron_purge',
	'wprm_global_enabled',
	'wprm_db_version',
	'wprm_demo_loaded',
	'wprm_demo_key_id',
];
foreach ( $options as $opt ) {
	delete_option( $opt );
}

// Remove all wprm_* options in case any extras were stored.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wprm_%'" ); // phpcs:ignore

// Delete all wprm CPT posts and their meta.
$post_types = [ 'wprm_endpoint', 'wprm_snippet' ];
foreach ( $post_types as $pt ) {
	$ids = get_posts( [ 'post_type' => $pt, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ] );
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}

// Unschedule CRON.
wp_clear_scheduled_hook( 'wprm_log_purge' );
