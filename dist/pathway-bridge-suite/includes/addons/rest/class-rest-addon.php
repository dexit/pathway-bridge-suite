<?php
/**
 * Class Rest_Addon
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * REST API Addon class.
 */
class Rest_Addon extends Addon {

	/**
	 * Handles the addon name.
	 *
	 * @var string
	 */
	public const TITLE = 'REST API';

	/**
	 * Handles the addon's API name.
	 *
	 * @var string
	 */
	public const NAME = 'rest';
}

Rest_Addon::setup();
