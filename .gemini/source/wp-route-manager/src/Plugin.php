<?php
declare( strict_types=1 );

namespace WP_Route_Manager;

use WP_Route_Manager\Admin\AdminLoader;
use WP_Route_Manager\Api\RouteRegistrar;
use WP_Route_Manager\Abilities\AbilitiesRegistrar;
use WP_Route_Manager\Cli\EndpointCommand;
use WP_Route_Manager\Cli\KeyCommand;
use WP_Route_Manager\Cli\LogCommand;
use WP_Route_Manager\Cli\TestCommand;
use WP_Route_Manager\Cli\ScaffoldCommand;
use WP_Route_Manager\Cron\LogPurge;
use WP_Route_Manager\Fields\FieldGroups;
use WP_Route_Manager\PostTypes\EndpointPostType;
use WP_Route_Manager\PostTypes\SnippetPostType;
use WP_Route_Manager\Storage\ChunkedImporter;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public function init(): void {
		load_plugin_textdomain( 'wp-route-manager', false, WPRM_DIR . 'languages' );

		// Post types.
		( new EndpointPostType() )->register();
		( new SnippetPostType() )->register();

		// SCF / ACF field groups.
		( new FieldGroups() )->register();

		// REST routes.
		add_action( 'rest_api_init', [ new RouteRegistrar(), 'register' ] );

		// Admin.
		if ( is_admin() ) {
			( new AdminLoader() )->boot();
		}

		// Abilities API (WP 6.9+).
		( new AbilitiesRegistrar() )->register();

		// CRON.
		( new LogPurge() )->schedule();

		// Queue / Chunked Importer.
		( new ChunkedImporter() )->register_hooks();

		// WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'wprm endpoint', EndpointCommand::class );
			\WP_CLI::add_command( 'wprm key',      KeyCommand::class );
			\WP_CLI::add_command( 'wprm log',      LogCommand::class );
			\WP_CLI::add_command( 'wprm test',     TestCommand::class );
			\WP_CLI::add_command( 'wprm scaffold', ScaffoldCommand::class );
		}

		// Extension hook — let other plugins hook in after we're bootstrapped.
		do_action( 'wprm_loaded', $this );
	}
}
