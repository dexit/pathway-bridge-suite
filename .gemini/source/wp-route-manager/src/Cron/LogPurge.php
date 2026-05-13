<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Cron;

use WP_Route_Manager\DB\LogRepository;

defined( 'ABSPATH' ) || exit;

final class LogPurge {

	private const HOOK = 'wprm_log_purge';

	public function schedule(): void {
		add_action( self::HOOK, [ $this, 'run' ] );

		if ( ! get_option( 'wprm_enable_cron_purge', 1 ) ) {
			$this->unschedule();
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	public function run(): void {
		$days = (int) get_option( 'wprm_log_retention_days', 7 );
		if ( $days <= 0 ) {
			return;
		}

		$deleted = ( new LogRepository() )->purge_old( $days );

		if ( $deleted > 0 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[WP Route Manager] Purged %d log entries older than %d days.', $deleted, $days ) );
		}

		do_action( 'wprm_logs_purged', $deleted, $days );
	}

	public function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}
}
