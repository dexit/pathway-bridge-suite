<?php
declare( strict_types=1 );

namespace WP_Route_Manager\DB;

defined( 'ABSPATH' ) || exit;

final class Schema {

	private const DB_VERSION = '1.1.0';
	private const OPTION_KEY = 'wprm_db_version';

	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$wpdb->prefix}wprm_api_keys (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			label VARCHAR(100) NOT NULL DEFAULT '',
			key_hash VARCHAR(64) NOT NULL DEFAULT '',
			key_prefix VARCHAR(12) NOT NULL DEFAULT '',
			allowed_endpoints LONGTEXT NULL,
			status TINYINT(1) NOT NULL DEFAULT 1,
			last_used_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY key_hash (key_hash),
			KEY status (status)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}wprm_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			endpoint_id BIGINT UNSIGNED NULL,
			endpoint_slug VARCHAR(200) NOT NULL DEFAULT '',
			key_id BIGINT UNSIGNED NULL,
			method VARCHAR(10) NOT NULL DEFAULT '',
			caller_ip VARCHAR(45) NOT NULL DEFAULT '',
			request_headers LONGTEXT NULL,
			request_params LONGTEXT NULL,
			request_body_raw LONGTEXT NULL,
			request_body_parsed LONGTEXT NULL,
			response_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
			response_body LONGTEXT NULL,
			duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
			snippet_output LONGTEXT NULL,
			error TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY endpoint_id (endpoint_id),
			KEY created_at (created_at),
			KEY response_code (response_code)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}wprm_payload_queue (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			endpoint_id BIGINT UNSIGNED NULL,
			endpoint_slug VARCHAR(200) NOT NULL DEFAULT '',
			payload LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
			scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			started_at DATETIME NULL,
			completed_at DATETIME NULL,
			error TEXT NULL,
			result LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY endpoint_id (endpoint_id),
			KEY scheduled_at (scheduled_at)
		) {$charset};" );

		update_option( self::OPTION_KEY, self::DB_VERSION );
	}

	public function uninstall(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wprm_api_keys" );     // phpcs:ignore
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wprm_logs" );          // phpcs:ignore
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wprm_payload_queue" ); // phpcs:ignore
	}

	public static function get_installed_version(): string {
		return (string) get_option( self::OPTION_KEY, '' );
	}
}
