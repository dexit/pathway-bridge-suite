<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Admin;

use WP_Route_Manager\Admin\QueueAdmin;
use WP_Route_Manager\PostTypes\EndpointPostType;
use WP_Route_Manager\PostTypes\SnippetPostType;

defined( 'ABSPATH' ) || exit;

final class AdminLoader {

	public function boot(): void {
		add_action( 'admin_menu',            [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices',         [ $this, 'toggled_notice' ] );

		// Wire SettingsAdmin::register() onto admin_init so WordPress can process
		// the options.php POST before the page renders. This is the canonical
		// Settings API pattern — settings_fields() + options_page require it.
		add_action( 'admin_init', [ $this, 'maybe_register_settings' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_wprm_delete_log',    [ $this, 'ajax_delete_log' ] );
		add_action( 'wp_ajax_wprm_clear_logs',    [ $this, 'ajax_clear_logs' ] );
		add_action( 'wp_ajax_wprm_create_key',    [ ApiKeyAdmin::class, 'ajax_create' ] );
		add_action( 'wp_ajax_wprm_delete_key',    [ ApiKeyAdmin::class, 'ajax_delete' ] );
		add_action( 'wp_ajax_wprm_toggle_key',    [ ApiKeyAdmin::class, 'ajax_toggle' ] );
		add_action( 'wp_ajax_wprm_get_log',       [ $this, 'ajax_get_log' ] );
		// Queue AJAX handled by ChunkedImporter (registered in Plugin::init).

		// Register SCF options pages (if SCF Pro / ACF Pro available).
		add_action( 'acf/init', [ $this, 'register_options_pages' ] );
	}

	/**
	 * Register native Settings API options on admin_init.
	 * Only runs when SCF options pages are NOT available (i.e. SCF Free).
	 * When ACF Pro / SCF Pro is active, settings are managed by FieldGroups via acf_add_options_sub_page().
	 */
	public function maybe_register_settings(): void {
		if ( function_exists( 'acf_add_options_page' ) ) {
			// SCF Pro / ACF Pro handles the settings page — native Settings API not needed.
			return;
		}
		( new SettingsAdmin() )->register();
	}

	public function register_menus(): void {
		add_menu_page(
			__( 'Route Manager', 'wp-route-manager' ),
			__( 'Route Manager', 'wp-route-manager' ),
			'manage_options',
			'wprm',
			[ $this, 'render_dashboard' ],
			'dashicons-rest-api',
			30
		);

		add_submenu_page(
			'wprm',
			__( 'Endpoints', 'wp-route-manager' ),
			__( 'Endpoints', 'wp-route-manager' ),
			'manage_options',
			'edit.php?post_type=' . EndpointPostType::POST_TYPE
		);

		add_submenu_page(
			'wprm',
			__( 'PHP Snippets', 'wp-route-manager' ),
			__( 'PHP Snippets', 'wp-route-manager' ),
			'manage_options',
			'edit.php?post_type=' . SnippetPostType::POST_TYPE
		);

		add_submenu_page(
			'wprm',
			__( 'API Keys', 'wp-route-manager' ),
			__( 'API Keys', 'wp-route-manager' ),
			'manage_options',
			'wprm-api-keys',
			[ new ApiKeyAdmin(), 'render' ]
		);

		add_submenu_page(
			'wprm',
			__( 'Payload Queue', 'wp-route-manager' ),
			__( 'Payload Queue', 'wp-route-manager' ),
			'manage_options',
			'wprm-queue',
			[ new QueueAdmin(), 'render' ]
		);

		add_submenu_page(
			'wprm',
			__( 'Request Logs', 'wp-route-manager' ),
			__( 'Request Logs', 'wp-route-manager' ),
			'manage_options',
			'wprm-logs',
			[ new LogAdmin(), 'render' ]
		);

		// Settings submenu: only add the native WP page when SCF Pro / ACF Pro is NOT active.
		// When ACF Pro/SCF Pro is active, register_options_pages() (called on acf/init) adds
		// a proper SCF options page instead.
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			add_submenu_page(
				'wprm',
				__( 'Settings', 'wp-route-manager' ),
				__( 'Settings', 'wp-route-manager' ),
				'manage_options',
				'wprm-settings',
				[ new SettingsAdmin(), 'render' ]
			);
		}
	}

	public function register_options_pages(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			// SCF Free detected — options page added via plain WP submenu in register_menus().
			return;
		}

		// SCF Pro / ACF Pro: register a proper options sub-page.
		// FieldGroups::register_settings_page_fields() will attach fields to it.
		acf_add_options_sub_page( [
			'page_title'  => __( 'Route Manager Settings', 'wp-route-manager' ),
			'menu_title'  => __( 'Settings', 'wp-route-manager' ),
			'parent_slug' => 'wprm',
			'menu_slug'   => 'wprm-settings',
			'capability'  => 'manage_options',
		] );
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-route-manager' ) );
		}
		require WPRM_DIR . 'templates/admin-dashboard.php';
	}

	public function enqueue_assets( string $hook ): void {
		$relevant_hooks = [
			'toplevel_page_wprm',
			'route-manager_page_wprm-api-keys',
			'route-manager_page_wprm-logs',
			'route-manager_page_wprm-queue',
			'route-manager_page_wprm-settings',
			'post.php',
			'post-new.php',
		];

		if ( ! in_array( $hook, $relevant_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wprm-admin',
			WPRM_URL . 'assets/css/admin.css',
			[],
			WPRM_VERSION
		);

		wp_enqueue_script(
			'wprm-admin',
			WPRM_URL . 'assets/js/admin.js',
			[ 'jquery', 'wp-util', 'clipboard' ],
			WPRM_VERSION,
			true
		);

		wp_localize_script( 'wprm-admin', 'wprmAdmin', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'wprm_admin' ),
			'namespace' => get_option( 'wprm_namespace', 'wprm' ),
			'version'   => get_option( 'wprm_api_version', 'v1' ),
			'restBase'  => rest_url(),
			'i18n'      => [
				'confirmDelete'  => __( 'Delete this item permanently? This cannot be undone.', 'wp-route-manager' ),
				'confirmClear'   => __( 'Clear ALL logs? This cannot be undone.', 'wp-route-manager' ),
				'copied'         => __( 'Copied!', 'wp-route-manager' ),
				'keyRevealOnce'  => __( 'This key will only be shown once. Copy it now.', 'wp-route-manager' ),
			],
		] );
	}

	public function toggled_notice(): void {
		if ( ! empty( $_GET['wprm_toggled'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Status updated.', 'wp-route-manager' )
				. '</p></div>';
		}
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_delete_log(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-route-manager' ), 403 );
		}

		$id = absint( $_POST['id'] ?? 0 );
		( new \WP_Route_Manager\DB\LogRepository() )->delete( $id );
		wp_send_json_success();
	}

	public function ajax_clear_logs(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-route-manager' ), 403 );
		}

		$endpoint_id = absint( $_POST['endpoint_id'] ?? 0 );
		$logs = new \WP_Route_Manager\DB\LogRepository();

		if ( $endpoint_id ) {
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'wprm_logs', [ 'endpoint_id' => $endpoint_id ], [ '%d' ] );
			wp_send_json_success();
		}

		$logs->clear_all();
		wp_send_json_success();
	}

	public function ajax_get_log(): void {
		check_ajax_referer( 'wprm_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-route-manager' ), 403 );
		}

		$id  = absint( $_GET['id'] ?? 0 );
		$log = ( new \WP_Route_Manager\DB\LogRepository() )->get( $id );

		if ( ! $log ) {
			wp_send_json_error( __( 'Log entry not found.', 'wp-route-manager' ), 404 );
		}

		wp_send_json_success( $log );
	}
}
