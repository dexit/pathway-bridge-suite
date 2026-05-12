<?php
/**
 * Class Rocketchat_Form_Bridge
 *
 * @package formsbridge
 */

namespace FORMS_BRIDGE;

use FBAPI;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Form bridge implementation for the Rocket.Chat API
 */
class Rocketchat_Form_Bridge extends Form_Bridge {
	/**
	 * Submits payload and attachments to the bridge's backend.
	 *
	 * @param array $payload Payload data.
	 * @param array $attachments Submission's attached files.
	 *
	 * @return array|WP_Error Http request response.
	 */
	public function submit( $payload = array(), $attachments = array() ) {
		$uploads = FBAPI::get_uploads();
		$room_id = $payload['roomId'] ?? $payload['channel'] ?? null;

		if ( ! empty( $uploads ) && $room_id ) {
			$attachments = Forms_Bridge::attachments( $uploads );
			$backend     = $this->backend();

			$message_attachments = array();
			foreach ( $attachments as $name => $path ) {
				$info     = pathinfo( $path );
				$filename = $info['basename'];

				$response = $backend->post(
					'/api/v1/rooms.media/' . $room_id,
					array(
						'msg' => $name,
					),
					array(
						'Content-Type' => 'multipart/form-data',
					),
					array(
						'file' => $path,
					)
				);

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				if ( ! $response['data']['success'] ) {
					return new WP_Error( 'rocketchat_upload', __( 'Can not upload a file to Rocket.Chat', 'forms-bridge' ), $response['data'] );
				}

				unset( $payload[ $name ] );
				unset( $payload[ $name . '_filename' ] );

				$message_attachments[] = array(
					'title'               => $filename,
					'title_link'          => $response['data']['file']['url'],
					'title_link_download' => true,
				);
			}

			$payload['attachments'] = $message_attachments;
		}

		return parent::submit( $payload, array() );
	}
}
