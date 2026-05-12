<?php
/**
 * Class Http_Setting
 *
 * @package httpbridge
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

namespace HTTP_BRIDGE;

use WPCT_PLUGIN\Singleton;
use WPCT_PLUGIN\Settings_Store;

use TypeError;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * HTTP setting to be registered on the plugins store.
 */
class Http_Setting extends Singleton {
	/**
	 * Handles the plugin store instance.
	 *
	 * @var Settings_Store
	 */
	private static $store;

	/**
	 * Http setting schema getter.
	 *
	 * @return array Json schema.
	 */
	public static function schema() {
		return array(
			'name'       => 'http',
			'properties' => array(
				'backends'    => array(
					'type'    => 'array',
					'items'   => Backend::schema(),
					'default' => array(),
				),
				'credentials' => array(
					'type'    => 'array',
					'items'   => Credential::schema(),
					'default' => array(),
				),
			),
			'required'   => array( 'backends', 'credentials' ),
			'default'    => array(
				'backends'    => array(),
				'credentials' => array(),
			),
		);
	}

	/**
	 * Public class initializator.
	 *
	 * @param Settings_Store $store Publin's store instance.
	 */
	public static function register( $store ) {
		self::get_instance( $store );
	}

	/**
	 * Setting instance getter.
	 *
	 * @return Settings_Store|null
	 */
	public static function setting() {
		if ( ! self::$store ) {
			return;
		}

		return self::$store::setting( 'http' );
	}

	/**
	 * Setting sanitization.
	 *
	 * @param array $data Setting data.
	 *
	 * @return array Sanitized data.
	 */
	public static function sanitize_setting( $data ) {
		$uniques  = array();
		$backends = array();

		foreach ( $data['backends'] ?? array() as $backend ) {
			if ( ! in_array( $backend['name'], $uniques, true ) ) {
				$uniques[]  = $backend['name'];
				$backends[] = $backend;
			}
		}

		$data['backends'] = $backends;

		$uniques     = array();
		$credentials = array();

		foreach ( $data['credentials'] ?? array() as $credential ) {
			if ( ! in_array( $credential['name'], $uniques, true ) ) {
				$uniques[]     = $credential['name'];
				$credentials[] = $credential;
			}
		}

		$data['credentials'] = $credentials;
		return $data;
	}

	/**
	 * Class constructor. Stores the store instance and registers the
	 * http setting to it.
	 *
	 * @param array{0: Settings_Store} ...$args Constructor arguments with a store on its
	 *                                          first position.
	 *
	 * @throws TypeError If $store is null.
	 */
	protected function construct( ...$args ) {
		$store = $args[0] ?? null;

		if ( ! ( $store instanceof Settings_Store ) ) {
			throw new TypeError();
		}

		self::$store = $store;

		add_action(
			'init',
			static function () {
				self::$store::register_setting( self::schema() );
			},
			5
		);

		self::$store::ready(
			static function ( $store ) {
				$store::use_setter(
					'http',
					static function ( $data ) {
						return self::sanitize_setting( $data );
					},
					9,
					1,
				);
			}
		);

		add_filter( 'http_bridge_backends', array( $this, 'get_backends' ), 10, 1 );
		add_filter( 'http_bridge_credentials', array( $this, 'get_credentials' ), 10, 1 );
	}

	/**
	 * Backends public filter callback.
	 *
	 * @param Backend[] $backends Array of backend instances.
	 *
	 * @return Backend[]
	 */
	public function get_backends( $backends ) {
		if ( ! wp_is_numeric_array( $backends ) ) {
			$backends = array();
		}

		if ( ! self::$store ) {
			return $backends;
		}

		$setting = self::$store::setting( 'http' );

		if ( ! $setting ) {
			return $backends;
		}

		foreach ( $setting->backends ?: array() as $data ) {
			$backends[] = new Backend( $data, $setting );
		}

		return $backends;
	}

	/**
	 * Credentials public filter callback.
	 *
	 * @param Credential[] $credentials Array of credential instances.
	 *
	 * @return Credential[]
	 */
	public function get_credentials( $credentials ) {
		if ( ! wp_is_numeric_array( $credentials ) ) {
			$credentials = array();
		}

		if ( ! self::$store ) {
			return $credentials;
		}

		$setting = self::$store::setting( 'http' );

		if ( ! $setting ) {
			return $credentials;
		}

		foreach ( $setting->credentials ?: array() as $data ) {
			$credentials[] = new Credential( $data, $setting );
		}

		return $credentials;
	}
}
