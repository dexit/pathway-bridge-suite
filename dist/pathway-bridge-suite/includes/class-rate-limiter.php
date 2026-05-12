<?php
/**
 * Rate Limiter
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Handles rate limiting for incoming requests.
 */
class Rate_Limiter {

	public static function check( $key, $limit, $period ) {
		$transient_key = 'pbs_rl_' . md5( $key );
		$count = (int) get_transient( $transient_key ) ?: 0;

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $transient_key, $count + 1, $period );
		return true;
	}
}
