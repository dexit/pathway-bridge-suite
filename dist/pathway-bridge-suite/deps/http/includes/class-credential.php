<?php
/**
 * Class Credential
 *
 * @package httpbridge
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

namespace HTTP_BRIDGE;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Http credential object.
 */
class Credential {

	/**
	 * Handles the oauth transient name. The transient will store
	 * ephemeral credential data used on oath redirections.
	 *
	 * @var string
	 */
	private const TRANSIENT = 'http-bridge-oauth-credential';

	/**
	 * Handles the oauth nonce name.
	 *
	 * @var string
	 */
	private const OAUTH_NONCE = 'http-bridge-oauth-nonce';

	/**
	 * Credential json schema getter.
	 *
	 * @return array
	 */
	public static function schema() {
		return array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => 'http-credential',
			'oneOf'   => array(
				array(
					'title'                => 'basic-credential',
					'type'                 => 'object',
					'properties'           => array(
						'name'          => array(
							'title'       => _x( 'Name', 'Credential schema', 'http-bridge' ),
							'description' => __( 'Unique name of the credential', 'http-bridge' ),
							'type'        => 'string',
							'minLength'   => 1,
						),
						'schema'        => array(
							'title'   => _x( 'Schema', 'Credential schema', 'http-bridge' ),
							'type'    => 'string',
							'enum'    => array( 'Basic', 'Token', 'URL' ),
							'default' => 'Basic',
						),
						'client_id'     => array(
							'title' => _x( 'Client ID', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
						'client_secret' => array(
							'title' => _x( 'Client secret', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
					),
					'required'             => array(
						'name',
						'schema',
						'client_id',
						'client_secret',
					),
					'additionalProperties' => false,
				),
				array(
					'title'                => 'digest-credential',
					'type'                 => 'object',
					'properties'           => array(
						'name'          => array(
							'title'       => _x( 'Name', 'Credential schema', 'http-bridge' ),
							'description' => __( 'Unique name of the credential', 'http-bridge' ),
							'type'        => 'string',
							'minLength'   => 1,
						),
						'schema'        => array(
							'title' => _x( 'Schema', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
							'enum'  => array( 'Digest' ),
						),
						'client_id'     => array(
							'title' => _x( 'Client ID', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
						'client_secret' => array(
							'title' => _x( 'Client secret', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
						'realm'         => array(
							'title' => _x( 'Realm', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
					),
					'required'             => array(
						'name',
						'schema',
						'client_id',
						'client_secret',
						'realm',
					),
					'additionalProperties' => false,
				),
				array(
					'title'                => 'rpc-credential',
					'type'                 => 'object',
					'properties'           => array(
						'name'          => array(
							'title'       => _x( 'Name', 'Credential schema', 'http-bridge' ),
							'description' => __( 'Unique name of the credential', 'http-bridge' ),
							'type'        => 'string',
							'minLength'   => 1,
						),
						'schema'        => array(
							'title' => _x( 'Schema', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
							'enum'  => array( 'RPC' ),
						),
						'client_id'     => array(
							'title' => _x( 'User login', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
						'client_secret' => array(
							'title' => _x( 'Password', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
						'database'      => array(
							'title' => _x( 'Database', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
					),
					'required'             => array(
						'name',
						'schema',
						'client_id',
						'client_secret',
						'database',
					),
					'additionalProperties' => false,
				),
				array(
					'title'                => 'bearer-credential',
					'type'                 => 'object',
					'properties'           => array(
						'name'         => array(
							'title'       => _x( 'Name', 'Credential schema', 'http-bridge' ),
							'description' => __( 'Unique name of the credential', 'http-bridge' ),
							'type'        => 'string',
							'minLength'   => 1,
						),
						'schema'       => array(
							'title' => _x( 'Schema', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
							'enum'  => array( 'Bearer' ),
						),
						'access_token' => array(
							'title' => _x( 'Acces token', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
						'expires_at'   => array(
							'title'   => _x( 'Expires at', 'Credential schema', 'http-bridge' ),
							'type'    => 'integer',
							'default' => time() + 60 * 60 * 24 * 365 * 100,
						),
					),
					'required'             => array( 'name', 'schema', 'access_token', 'expires_at' ),
					'additionalProperties' => false,
				),
				array(
					'title'                => 'oauth-credential',
					'type'                 => 'object',
					'properties'           => array(
						'name'                     => array(
							'title'       => _x( 'Name', 'Credential schema', 'http-bridge' ),
							'description' => __( 'Unique name of the credential', 'http-bridge' ),
							'type'        => 'string',
							'minLength'   => 1,
						),
						'schema'                   => array(
							'title' => _x( 'Schema', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
							'enum'  => array( 'OAuth' ),
						),
						'oauth_url'                => array(
							'title'  => _x( 'Authorization URL', 'Credential schema', 'http-bridge' ),
							'type'   => 'string',
							'format' => 'uri',
						),
						'client_id'                => array(
							'title' => _x( 'Client ID', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
						'client_secret'            => array(
							'title' => _x( 'Client secret', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
						'scope'                    => array(
							'title' => _x( 'Scope', 'Credential schema', 'http-bridge' ),
							'type'  => 'string',
						),
						'access_token'             => array(
							'title'   => _x( 'Access token', 'Credential schema', 'http-bridge' ),
							'type'    => 'string',
							'default' => '',
							'public'  => false,
						),
						'expires_at'               => array(
							'title'   => _x( 'Expires at', 'Credential schema', 'http-bridge' ),
							'type'    => 'integer',
							'default' => 0,
							'public'  => false,
						),
						'refresh_token'            => array(
							'title'   => _x( 'Refresh token', 'Credential schema', 'http-bridge' ),
							'type'    => 'string',
							'default' => '',
							'public'  => false,
						),
						'refresh_token_expires_at' => array(
							'title'   => _x( 'Refresh token expires at', 'Credential schema', 'http-bridge' ),
							'type'    => 'integer',
							'default' => 0,
							'public'  => false,
						),
						'pkce'                     => array(
							'title'   => _x( 'PKCE compliant', 'Credential schema', 'http-bridge' ),
							'type'    => 'boolean',
							'default' => false,
							'public'  => false,
						),
					),
					'required'             => array(
						'name',
						'schema',
						'oauth_url',
						'client_id',
						'client_secret',
						// 'scope',
						'access_token',
						'expires_at',
						'refresh_token',
					),
					'additionalProperties' => true,
				),
			),
		);
	}

	/**
	 * Ephemeral credential registration as an interceptor to allow
	 * api fetch, ping and introspection with non registered credentials.
	 *
	 * @param array $data Credential data.
	 */
	public static function temp_registration( $data ) {
		if ( ! $data ) {
			return;
		}

		add_filter(
			'http_bridge_credentials',
			static function ( $credentials ) use ( $data ) {
				foreach ( $credentials as $candidate ) {
					if ( $candidate->name === $data['name'] ) {
						$credential = $candidate;
					}
				}

				if ( ! isset( $credential ) ) {
					$credential = new static( $data );

					if ( $credential->is_valid ) {
						$credentials[] = $credential;
					}
				}

				return $credentials;
			},
			99,
			2
		);
	}

	/**
	 * OAuth transient credential getter.
	 *
	 * @return Credential|null
	 */
	public static function get_transient() {
		$data = get_transient( static::TRANSIENT );

		if ( ! $data ) {
			wp_die( esc_html( __( 'Invalid oatuh redirect request', 'http-bridge' ) ) );
			return;
		} else {
			delete_transient( static::TRANSIENT );
		}

		$credential = new static( $data );
		if ( ! $credential->is_valid ) {
			return;
		}

		return $credential;
	}

	/**
	 * Handles credential data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Credential constructor. Apply a data validation before before it is stored
	 * on the object.
	 *
	 * @param array $data Credential data.
	 */
	public function __construct( $data ) {
		$this->data = wpct_plugin_sanitize_with_schema( $data, static::schema() );
	}

	/**
	 * Object properties access interceptor. Proxies object properties to
	 * data attributes and performs some access control to values.
	 *
	 * @param string $name Property name.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'is_valid':
				return ! is_wp_error( $this->data );
			case 'client_id':
				if ( ! $this->is_valid ) {
					return;
				}

				return $this->data['client_id'] ?? $this->data['user'];
			case 'client_secret':
				if ( ! $this->is_valid ) {
					return;
				}

				return $this->data['client_secret'] ?? $this->data['password'];
			case 'realm':
				if ( ! $this->is_valid ) {
					return;
				}

				return $this->data['realm'] ??
					( $this->data['database'] ?? $this->data['scope'] );
			case 'access_token':
			case 'refresh_token':
				return;
			case 'authorized':
				return $this->is_valid && ! empty( $this->data['access_token'] );
			default:
				if ( ! $this->is_valid ) {
					return;
				}

				return $this->data[ $name ] ?? null;
		}
	}

	/**
	 * Gets the credential HTTP authorization.
	 *
	 * @return mixed
	 */
	public function authorization() {
		switch ( $this->schema ) {
			case 'RPC':
				return array(
					$this->database,
					$this->client_id,
					$this->client_secret,
				);
			case 'Bearer':
			case 'OAuth':
				$access_token = $this->get_access_token();
				if ( ! $access_token ) {
					return;
				}

				return 'Bearer ' . $access_token;
			case 'Basic':
				return 'Basic ' . base64_encode( "{$this->client_id}:{$this->client_secret}" );
			case 'Token':
				return "token {$this->client_id}:{$this->client_secret}";
			case 'URL':
				return "{$this->client_id}:{$this->client_secret}";
		}
	}

	/**
	 * Gets the OAuth authorization URL of the credential.
	 *
	 * @param string $verb Auth action to be performed (token, grant, revoke).
	 *
	 * @return string
	 */
	public function oauth_url( $verb ) {
		return apply_filters(
			'http_bridge_oauth_url',
			$this->oauth_url . '/' . $verb,
			$verb,
			$this
		);
	}

	/**
	 * Gets the OAuth redirect endpoint.
	 *
	 * @return string
	 */
	public function oauth_redirect_uri() {
		return get_rest_url() . 'http-bridge/v1/oauth/redirect';
	}

	/**
	 * Performs a token request to the the oauth url of the credential.
	 *
	 * @param array $query Request query.
	 *
	 * @return array|WP_Error
	 */
	private function oauth_token_request( $query ) {
		$url = $this->oauth_url( 'token' );

		$query['client_id']     = $this->client_id;
		$query['client_secret'] = $this->client_secret;

		$response = http_bridge_post(
			$url,
			$query,
			array( 'Content-Type' => 'application/x-www-form-urlencoded' )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response['data'];

		if ( isset( $data['error'] ) ) {
			return new WP_Error( $data['error'] );
		}

		return $data;
	}

	/**
	 * Performs a revoke token request to the oauth url of the credential and removes
	 * stored tokens from the database.
	 */
	private function revoke_refresh_token() {
		if ( ! empty( $this->data['refresh_token'] ) ) {
			$url   = $this->oauth_url( 'token/revoke' );
			$query = array( 'token' => $this->data['refresh_token'] );

			$response = http_bridge_post(
				$url,
				$query,
				array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				)
			);

			// if (is_wp_error($response)) {
			// return false;
			// }
		}

		return $this->update_tokens(
			array(
				'access_token'             => '',
				'refresh_token'            => '',
				'expires_at'               => 0,
				'refresh_token_expires_at' => 0,
			)
		);
	}

	/**
	 * Updates credential tokens and write them to the database.
	 *
	 * @param array $tokens OAuth tokens.
	 *
	 * @return boolean Update result.
	 */
	public function update_tokens( $tokens ) {
		$data                 = $this->data;
		$data['enabled']      = true;
		$data['access_token'] = $tokens['access_token'];
		$data['expires_at']   = ( $tokens['expires_in'] ?? 0 ) + time() - 10;

		if ( isset( $tokens['refresh_token'] ) ) {
			$data['refresh_token'] = $tokens['refresh_token'];

			if ( isset( $tokens['refresh_token_expires_in'] ) ) {
				$data['refresh_token_expires_at'] = $tokens['refresh_token_expires_in'] + time() - 10;
			}
		}

		$data = apply_filters(
			'http_bridge_oauth_update_tokens',
			$data,
			$this,
		);

		$credential = new static( $data );
		return $credential->save();
	}

	/**
	 * Refresh oauth access token.
	 *
	 * @return string|null Renewed access token, or null.
	 */
	private function refresh_access_token() {
		if ( ! $this->is_valid ) {
			return;
		}

		if ( 'Bearer' === $this->data['schema'] ) {
			return $this->data['access_token'] ?? null;
		}

		if ( empty( $this->data['refresh_token'] ) ) {
			return;
		}

		$pre = apply_filters(
			'http_bridge_pre_refresh_access_token',
			null,
			$this,
		);

		if ( $pre ) {
			return $pre;
		}

		$tokens = $this->oauth_token_request(
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $this->data['refresh_token'],
			)
		);

		if ( is_wp_error( $tokens ) ) {
			return;
		}

		if ( $this->update_tokens( $tokens ) ) {
			return $tokens['access_token'];
		}
	}

	/**
	 * Credential's access token public getter.
	 *
	 * @return string|null
	 */
	public function get_access_token() {
		if ( ! $this->is_valid ) {
			return;
		}

		$access_token = $this->data['access_token'] ?? null;
		if ( ! $access_token ) {
			return;
		}

		if ( $this->expires_at <= time() ) {
			$expires_at = $this->refresh_token_expires_at;
			if ( $expires_at && $expires_at <= time() ) {
				return;
			}

			return $this->refresh_access_token();
		}

		return $access_token;
	}

	/**
	 * Revokes credential oauth tokens and remove them from the database.
	 *
	 * @return boolean Revoke result.
	 */
	public function oauth_revoke() {
		if ( ! $this->is_valid ) {
			return false;
		}

		$pre = apply_filters(
			'http_bridge_pre_oauth_revoke',
			null,
			$this,
		);

		if ( null !== $pre ) {
			return $pre;
		}

		if ( ! empty( $this->data['refresh_token'] ) ) {
			$result = $this->revoke_refresh_token();

			if ( ! $result ) {
				return new WP_Error( 'internal_server_error' );
			}
		} else {
			$result = $this->update_tokens(
				array(
					'access_token'             => '',
					'refresh_token'            => '',
					'expires_at'               => 0,
					'refresh_token_expires_at' => 0,
				)
			);
		}

		return $result;
	}

	/**
	 * Stores the credential data as the oauth transient for 10 minutes.
	 *
	 * @return array|WP_Error;
	 */
	public function oauth_grant_transient() {
		if ( ! $this->is_valid ) {
			return new WP_Error( 'invalid_credential' );
		}

		$auth_url = $this->oauth_url( 'auth' );

		$data         = $this->data;
		$data['pkce'] = (bool) apply_filters( 'http_bridge_oauth_use_pkce', false, $auth_url, $this );

		$success = set_transient( static::TRANSIENT, $data, 600 );
		if ( ! $success ) {
			return array( 'success' => $success );
		}

		$oauth_nonce = $this->oauth_nonce();

		$params = array(
			'redirect_uri'  => $this->oauth_redirect_uri(),
			'client_id'     => $this->client_id,
			'access_type'   => 'offline',
			'response_type' => 'code',
			'state'         => self::OAUTH_NONCE . '-' . $oauth_nonce,
		);

		if ( $this->scope ) {
			$params['scope'] = $this->scope;
		}

		if ( $data['pkce'] ) {
			$code_verifier                   = substr( wp_hash( $oauth_nonce, 'auth', 'sha256' ), 0, 128 );
			$code_challenge                  = wp_hash( $code_verifier, 'auth', 'sha256' );
			$params['code_challenge']        = rtrim( strtr( base64_encode( $code_challenge ), '+/', '-_' ), '=' );
			$params['code_challenge_method'] = 'S256';
		}

		$params = apply_filters(
			'http_bridge_oauth_auth_request_params',
			$params,
			$auth_url,
			$this,
		);

		return array(
			'success' => $success,
			'data'    => array(
				'url'    => $auth_url,
				'params' => $params,
			),
		);
	}

	/**
	 * OAuth HTTP redirection callback. The method will be executed by
	 * a transient credential on the authorization flow after a redirection
	 * to the oauth endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return boolean
	 */
	public function oauth_redirect_callback( $request ) {
		if ( ! $this->is_valid ) {
			return false;
		}

		$pre = apply_filters(
			'http_bridge_pre_oauth_redirect',
			null,
			$this,
			$request,
		);

		if ( null !== $pre ) {
			return $pre;
		}

		if ( false && ( $request['error'] || isset( $_REQUEST['error'] ) ) ) {
			return false;
		}

		if ( ! empty( $request['state'] ) ) {
			$nonce = str_replace( self::OAUTH_NONCE . '-', '', $request['state'] );
			if ( $nonce !== $this->oauth_nonce() ) {
				return false;
			}
		}

		if ( $this->pkce ) {
			if ( empty( $request['code_challenge'] ) ) {
				return false;
			}

			$nonce              = $nonce ?? $this->oauth_nonce();
			$code_verifier      = substr( wp_hash( $nonce, 'auth', 'sha256' ), 0, 128 );
			$code_challenge     = wp_hash( $code_verifier, 'auth', 'sha256' );
			$url_code_challenge = rtrim( strtr( base64_encode( $code_challenge ), '+/', '-_' ), '=' );

			if ( $url_code_challenge !== $request['code_challenge'] ) {
				return false;
			}
		}

		$token_req_params = array(
			'grant_type'   => 'authorization_code',
			'code'         => $request['code'],
			'redirect_uri' => $this->oauth_redirect_uri(),
		);

		if ( $this->pkce ) {
			$token_req_params['code_verifier'] = $code_verifier;
		}

		$tokens = $this->oauth_token_request( $token_req_params );

		if ( ! $tokens || is_wp_error( $tokens ) ) {
			return false;
		}

		return $this->update_tokens( $tokens );
	}

	/**
	 * Create a nonce for OAuth authorization requests.
	 *
	 * @return string
	 */
	private function oauth_nonce() {
		$token  = wp_get_session_token();
		$action = self::OAUTH_NONCE;
		$tick   = wp_nonce_tick( $action );

		return substr( wp_hash( $tick . '|' . $action . '|' . $token ), -12, 10 );
	}

	/**
	 * Credential's data getter.
	 *
	 * @return array|null
	 */
	public function data() {
		if ( ! $this->is_valid ) {
			return null;
		}

		return $this->data;
	}

	/**
	 * Persist the credential on the database.
	 *
	 * @return boolean Database write result.
	 */
	public function save() {
		if ( ! $this->is_valid ) {
			return false;
		}

		$setting = Http_Setting::setting();
		if ( ! $setting ) {
			return false;
		}

		$credentials = $setting->credentials;
		if ( ! wp_is_numeric_array( $credentials ) ) {
			return false;
		}

		$index = array_search( $this->name, array_column( $credentials, 'name' ), true );

		if ( false === $index ) {
			$credentials[] = $this->data;
		} else {
			$credentials[ $index ] = $this->data;
		}

		$setting->credentials = $credentials;

		return true;
	}

	/**
	 * Removes the credential from the database.
	 *
	 * @retun boolean Database deletion result.
	 */
	public function delete() {
		if ( $this->is_valid ) {
			return false;
		}

		$setting = Http_Setting::setting();
		if ( ! $setting ) {
			return false;
		}

		$credentials = $setting->credentials;
		if ( ! wp_is_numeric_array( $credentials ) ) {
			return false;
		}

		$index = array_search( $this->name, array_column( $credentials, 'name' ), true );

		if ( false === $index ) {
			return false;
		}

		array_splice( $credentials, $index, 1 );
		$setting->credentials = $credentials;

		return true;
	}
}
