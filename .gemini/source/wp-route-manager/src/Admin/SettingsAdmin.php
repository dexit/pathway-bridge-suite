<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Fallback settings page using the native Settings API.
 *
 * Used when SCF Free is active (no options-page support).
 * When ACF Pro / SCF Pro is active, FieldGroups registers
 * an SCF options page instead and this class is not used.
 *
 * register() must fire on admin_init so WordPress can handle
 * the options.php POST before render() is ever called.
 */
final class SettingsAdmin {

	public const OPTION_GROUP = 'wprm_settings';

	/**
	 * Called on admin_init — registers all settings so options.php can save them.
	 * Also called from render() as a safety net for direct page loads.
	 */
	public function register(): void {
		register_setting(
			self::OPTION_GROUP,
			'wprm_global_enabled',
			[ 'sanitize_callback' => 'absint', 'default' => 1 ]
		);
		register_setting(
			self::OPTION_GROUP,
			'wprm_namespace',
			[ 'sanitize_callback' => 'sanitize_key', 'default' => 'wprm' ]
		);
		register_setting(
			self::OPTION_GROUP,
			'wprm_api_version',
			[ 'sanitize_callback' => 'sanitize_key', 'default' => 'v1' ]
		);
		register_setting(
			self::OPTION_GROUP,
			'wprm_global_logging',
			[ 'sanitize_callback' => 'absint', 'default' => 1 ]
		);
		register_setting(
			self::OPTION_GROUP,
			'wprm_log_retention_days',
			[ 'sanitize_callback' => 'absint', 'default' => 7 ]
		);
		register_setting(
			self::OPTION_GROUP,
			'wprm_enable_cron_purge',
			[ 'sanitize_callback' => 'absint', 'default' => 1 ]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-route-manager' ) );
		}
		// register() may not have fired yet on direct page loads — safe to call twice.
		$this->register();
		require WPRM_DIR . 'templates/admin-settings.php';
	}
}
