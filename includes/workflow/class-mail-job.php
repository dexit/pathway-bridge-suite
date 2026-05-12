<?php
/**
 * WP Mail Workflow Job
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Workflow;

use PATHWAY_BRIDGE_SUITE\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Job to send emails using native wp_mail.
 */
class Mail_Job {

	public static function run( $payload, $bridge, $job ) {
		$config = $job->data;

		$to      = self::replace_placeholders( $config['to'] ?? get_option( 'admin_email' ), $payload );
		$subject = self::replace_placeholders( $config['subject'] ?? 'Pathway Bridge Notification', $payload );
		$message = self::replace_placeholders( $config['message'] ?? '', $payload );
		$headers = $config['headers'] ?? array( 'Content-Type: text/html; charset=UTF-8' );

		$attachments = array();
		if ( ! empty( $config['attachments'] ) ) {
			foreach ( (array) $config['attachments'] as $field_id ) {
				if ( isset( $payload['files'][ $field_id ]['path'] ) ) {
					$attachments[] = $payload['files'][ $field_id ]['path'];
				}
			}
		}

		Logger::log( "Sending WP Mail to: $to", Logger::INFO );

		$success = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( ! $success ) {
			Logger::log( "WP Mail failed to send to $to", Logger::ERROR );
		}

		return $payload;
	}

	private static function replace_placeholders( $input, $payload ) {
		if ( ! is_string( $input ) ) return $input;
		return preg_replace_callback( '/\{([^\}]+)\}/', function ( $matches ) use ( $payload ) {
			$key = $matches[1];
			return $payload[ $key ] ?? $matches[0];
		}, $input );
	}
}
