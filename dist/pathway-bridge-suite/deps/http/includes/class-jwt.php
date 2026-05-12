<?php

namespace HTTP_BRIDGE;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * JWT REST API authentication.
 */
class JWT {

	/**
	 * Auth secret getter.
	 *
	 * @return string
	 */
	private function secret() {
		if ( defined( 'HTTP_BRIDGE_AUTH_SECRET' ) ) {
			return HTTP_BRIDGE_AUTH_SECRET;
		}

		$secret = get_option( 'http-bridge-jwt-secret' );

		if ( ! $secret ) {
			$chars  =
				'0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$len    = strlen( $chars );
			$secret = '';
			for ( $i = 0; $i < 20; $i++ ) {
				$secret .= $chars[ random_int( 0, $len - 1 ) ];
			}

			add_option( 'http-bridge-jwt-secret', $secret );
		}

		return $secret;
	}

	/**
	 * Get encoded payload token.
	 *
	 * @param array $payload Token payload.
	 *
	 * @return string JWT encoded token.
	 */
	public function encode( $payload ) {
		$header = wp_json_encode(
			array(
				'alg' => 'HS256',
				'typ' => 'JWT',
			)
		);

		$header  = $this->base64URLEncode( $header );
		$payload = wp_json_encode( $payload );
		$payload = $this->base64URLEncode( $payload );

		$signature = hash_hmac(
			'sha256',
			$header . '.' . $payload,
			$this->secret(),
			true
		);
		$signature = $this->base64URLEncode( $signature );
		return $header . '.' . $payload . '.' . $signature;
	}

	/**
	 * Get decoded token payload.
	 *
	 * @param string $token JWT encoded token.
	 *
	 * @return array Token payload.
	 */
	public function decode( $token ) {
		if (
			preg_match(
				'/^(?<header>.+)\.(?<payload>.+)\.(?<signature>.+)$/',
				$token,
				$matches
			) !== 1
		) {
			throw new Exception( 'Invalid token format', 400 );
		}

		$signature = hash_hmac(
			'sha256',
			$matches['header'] . '.' . $matches['payload'],
			$this->secret(),
			true
		);

		$signature_from_token = $this->base64URLDecode( $matches['signature'] );

		if ( ! hash_equals( $signature, $signature_from_token ) ) {
			throw new Exception( 'Signature doesn\'t match', 401 );
		}

		$payload = json_decode(
			$this->base64URLDecode( $matches['payload'] ),
			true
		);
		return $payload;
	}

	/**
	 * URL conformant base64 encoder.
	 *
	 * @param string $text Source string.
	 *
	 * @return string Encoded string.
	 */
	private function base64URLEncode( $text ) {
		return str_replace(
			array( '+', '/', '=' ),
			array( '-', '_', '' ),
			base64_encode( $text )
		);
	}

	/**
	 * URL conformant base64 decoder.
	 *
	 * @param string $base64 Encoded string.
	 *
	 * @return string Decoded string.
	 */
	private function base64URLDecode( $text ) {
		return base64_decode( str_replace( array( '-', '_' ), array( '+', '/' ), $text ) );
	}
}
