<?php
/**
 * Webhook Workflow Job
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Workflow;

use HTTP_BRIDGE\Backend;
use PATHWAY_BRIDGE_SUITE\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Job to send data to multiple webhook recipients.
 */
class Webhook_Job {

	public static function run( $payload, $bridge, $job ) {
		$config     = $job->data;
		$recipients = $config['recipients'] ?? array();

		if ( empty( $recipients ) ) {
			// Fallback to a single recipient if defined in root config
			if ( ! empty( $config['url'] ) ) {
				$recipients[] = array( 'url' => $config['url'], 'method' => $config['method'] ?? 'POST' );
			}
		}

		$results = array();
		foreach ( $recipients as $recipient ) {
			$results[] = self::send_webhook( $payload, $recipient );
		}

		if ( ! empty( $config['response_field'] ) ) {
			$payload[ $config['response_field'] ] = $results;
		}

		return $payload;
	}

	private static function send_webhook( $payload, $recipient ) {
		$url     = $recipient['url'] ?? '';
		$method  = strtoupper( $recipient['method'] ?? 'POST' );
		$headers = $recipient['headers'] ?? array();

		if ( empty( $url ) ) return array( 'status' => 'error', 'message' => 'Empty URL' );

		Logger::log( "Sending Webhook to: $url", Logger::INFO );

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 15,
		);

		if ( 'GET' === $method ) {
			$url = add_query_arg( $payload, $url );
		} else {
			$args['body'] = wp_json_encode( $payload );
			if ( ! isset( $args['headers']['Content-Type'] ) ) {
				$args['headers']['Content-Type'] = 'application/json';
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::log( "Webhook failed: " . $response->get_error_message(), Logger::ERROR );
			return array( 'status' => 'error', 'message' => $response->get_error_message(), 'url' => $url );
		}

		$code = wp_remote_retrieve_response_code( $response );
		return array( 'status' => 'success', 'code' => $code, 'url' => $url );
	}
}
