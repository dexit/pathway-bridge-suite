<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WP Route Manager abilities via the WordPress Abilities API (WP 6.9+).
 *
 * Abilities tell external clients (Block Editor, other plugins, JS) what
 * capabilities this plugin provides — enabling integration points.
 *
 * Ability IDs follow the pattern: wprm/{ability}
 *
 * REST access: GET /wp-json/wp-abilities/v1/abilities?namespace=wprm
 *
 * @see https://make.wordpress.org/core/2025/09/12/abilities-api/
 */
final class AbilitiesRegistrar {

	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // WP < 6.9.
		}

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init',            [ $this, 'register_abilities' ] );
	}

	public function register_category(): void {
		wp_register_ability_category( 'wprm', [
			'label'       => __( 'WP Route Manager', 'wp-route-manager' ),
			'description' => __( 'Abilities provided by the WP Route Manager plugin.', 'wp-route-manager' ),
		] );
	}

	public function register_abilities(): void {
		// ── Core feature flags ────────────────────────────────────────────────

		wp_register_ability( 'wprm/php-snippets-enabled', [
			'label'       => __( 'PHP Snippets Enabled', 'wp-route-manager' ),
			'description' => __( 'Whether PHP snippet execution is enabled (requires WPRM_ALLOW_PHP_SNIPPETS constant).', 'wp-route-manager' ),
			'category'    => 'wprm',
			'value'       => WPRM_ALLOW_PHP_SNIPPETS,
			'meta'        => [
				'readonly'    => true,
				'show_in_rest' => true,
			],
		] );

		wp_register_ability( 'wprm/logging-enabled', [
			'label'       => __( 'Request Logging Enabled', 'wp-route-manager' ),
			'description' => __( 'Whether global request/response logging is active.', 'wp-route-manager' ),
			'category'    => 'wprm',
			'value'       => (bool) get_option( 'wprm_global_logging', true ),
			'meta'        => [
				'readonly'    => true,
				'show_in_rest' => true,
			],
		] );

		wp_register_ability( 'wprm/api-enabled', [
			'label'       => __( 'API Active', 'wp-route-manager' ),
			'description' => __( 'Whether the Route Manager REST API is globally enabled.', 'wp-route-manager' ),
			'category'    => 'wprm',
			'value'       => (bool) get_option( 'wprm_global_enabled', true ),
			'meta'        => [
				'readonly'    => true,
				'show_in_rest' => true,
			],
		] );

		wp_register_ability( 'wprm/api-namespace', [
			'label'    => __( 'API Namespace', 'wp-route-manager' ),
			'category' => 'wprm',
			'value'    => get_option( 'wprm_namespace', 'wprm' ),
			'meta'     => [
				'readonly'    => true,
				'show_in_rest' => true,
			],
		] );

		wp_register_ability( 'wprm/api-version', [
			'label'    => __( 'API Version', 'wp-route-manager' ),
			'category' => 'wprm',
			'value'    => get_option( 'wprm_api_version', 'v1' ),
			'meta'     => [
				'readonly'    => true,
				'show_in_rest' => true,
			],
		] );

		// ── Statistics abilities (admin only) ─────────────────────────────────

		if ( current_user_can( 'manage_options' ) ) {
			global $wpdb;

			$endpoint_count = (int) wp_count_posts( 'wprm_endpoint' )->publish;
			$snippet_count  = (int) wp_count_posts( 'wprm_snippet' )->publish;
			$log_count      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wprm_logs" ); // phpcs:ignore

			wp_register_ability( 'wprm/active-endpoint-count', [
				'label'    => __( 'Active Endpoints', 'wp-route-manager' ),
				'category' => 'wprm',
				'value'    => $endpoint_count,
				'meta'     => [ 'readonly' => true, 'show_in_rest' => true ],
			] );

			wp_register_ability( 'wprm/active-snippet-count', [
				'label'    => __( 'Active Snippets', 'wp-route-manager' ),
				'category' => 'wprm',
				'value'    => $snippet_count,
				'meta'     => [ 'readonly' => true, 'show_in_rest' => true ],
			] );

			wp_register_ability( 'wprm/log-entry-count', [
				'label'    => __( 'Log Entries', 'wp-route-manager' ),
				'category' => 'wprm',
				'value'    => $log_count,
				'meta'     => [ 'readonly' => true, 'show_in_rest' => true ],
			] );
		}

		// Allow third-party plugins to register their own wprm/* abilities.
		do_action( 'wprm_register_abilities' );
	}
}
