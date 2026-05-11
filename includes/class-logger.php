<?php
/**
 * Unified Logger
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE;

use WP_Error;
use WP_REST_Server;
use WPCT_PLUGIN\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Unified logger for the Pathway Bridge Suite.
 */
class Logger extends Singleton {

	private const FILE = 'pathway-bridge.log';
	public const ERROR = 'ERROR';
	public const INFO  = 'INFO';
	public const DEBUG = 'DEBUG';

	private static function log_path() {
		$dir = wp_upload_dir()['basedir'] . '/pathway-bridge-suite';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir . '/' . self::FILE;
	}

	public static function logs( $lines = 500 ) {
		$log_path = self::log_path();
		if ( ! file_exists( $log_path ) ) return array();

		$file = file( $log_path );
		return array_slice( $file, -$lines );
	}

	public static function log( $data, $level = 'DEBUG' ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			$data = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		}

		$line = sprintf( "[%s] [%s] %s\n", date( 'Y-m-d H:i:s' ), $level, $data );
		file_put_contents( self::log_path(), $line, FILE_APPEND );
	}

	public static function is_active() {
		return true; // Always active for now to ensure we capture initial setup
	}

	public static function setup() {
		return self::get_instance();
	}

	protected function construct( ...$args ) {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'pathway-bridge/v1', '/logs/', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					return self::logs();
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			) );
		} );
	}
}

Logger::setup();
