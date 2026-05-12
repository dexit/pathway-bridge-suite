<?php
/**
 * Class GCalendar_Post_Bridge
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Post bridge implementation for the Google Calendar service.
 */
class GCalendar_Post_Bridge extends Post_Bridge {

	/**
	 * Bridge constructor with addon name provisioning.
	 *
	 * @param array $data Bridge data.
	 */
	public function __construct( $data ) {
		parent::__construct( $data, 'gcalendar' );
		$this->data['single_endpoint'] = rtrim( $data['endpoint'], '/' ) . '/{id}';
	}

	/**
	 * Fetches the events from the bridge calendar.
	 *
	 * @param array $params Request params.
	 * @param array $headers HTTP headers.
	 *
	 * @return array|WP_Error Backend entries data.
	 */
	public function fetch_all( $params = array(), $headers = array() ) {
		$response = parent::fetch_all( $params, $headers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['items'];
	}

	/**
	 * Transforms the form payload into a Google Calendar event structure.
	 *
	 * @param array $payload Form submission payload.
	 *
	 * @return array|WP_Error Calendar event data.
	 */
	private function transform_to_event( $payload ) {
		$event = array();

		if ( isset( $payload['summary'] ) ) {
			$event['summary'] = $payload['summary'];
		}

		if ( isset( $payload['description'] ) ) {
			$event['description'] = $payload['description'];
		}

		if ( isset( $payload['location'] ) ) {
			$event['location'] = $payload['location'];
		}

		if ( isset( $payload['start'] ) ) {
			$event['start'] = $this->parse_datetime( $payload['start'] );
		}

		if ( isset( $payload['end'] ) ) {
			$event['end'] = $this->parse_datetime( $payload['end'] );
		}

		if ( ! isset( $event['end'] ) && isset( $event['start']['dateTime'] ) ) {
			$start_time   = strtotime( $event['start']['dateTime'] );
			$end_time     = $start_time + 3600;
			$event['end'] = array(
				'dateTime' => gmdate( 'Y-m-d\TH:i:s', $end_time ),
				'timeZone' => $event['start']['timeZone'],
			);
		}

		if ( isset( $payload['attendees'] ) ) {
			$attendees = array();
			if ( is_string( $payload['attendees'] ) ) {
				$emails = array_map( 'trim', explode( ',', $payload['attendees'] ) );
				foreach ( $emails as $email ) {
					if ( is_email( $email ) ) {
						$attendees[] = array( 'email' => $email );
					}
				}
			} elseif ( is_array( $payload['attendees'] ) ) {
				foreach ( $payload['attendees'] as $attendee ) {
					if ( is_string( $attendee ) && is_email( $attendee ) ) {
						$attendees[] = array( 'email' => $attendee );
					} elseif ( is_array( $attendee ) && isset( $attendee['email'] ) ) {
						$attendees[] = $attendee;
					}
				}
			}

			if ( ! empty( $attendees ) ) {
				$event['attendees'] = $attendees;
			}
		}

		if ( isset( $payload['reminders'] ) ) {
			$event['reminders'] = $payload['reminders'];
		}

		if ( isset( $payload['colorId'] ) ) {
			$event['colorId'] = $payload['colorId'];
		}

		if ( isset( $payload['sendUpdates'] ) ) {
			$event['sendUpdates'] = (bool) $payload['sendUpdates'];
		}

		if ( ! ( isset( $event['start'] ) && isset( $event['end'] ) ) ) {
			return new WP_Error(
				'missing_event_dates',
				'Event must have a start and an end date',
				$payload,
			);
		}

		if ( ! isset( $event['summary'] ) ) {
			return new WP_Error(
				'missing_summary',
				'Event must have a summary (title)',
				$payload
			);
		}

		return $event;
	}

	/**
	 * Parses a datetime value into Google Calendar format.
	 *
	 * @param mixed $datetime DateTime value (timestamp, string, or array).
	 *
	 * @return array DateTime structure for Google Calendar.
	 */
	private function parse_datetime( $datetime ) {
		if ( is_array( $datetime ) && isset( $datetime['dateTime'] ) ) {
			return $datetime;
		}

		$timezone = wp_timezone_string();

		if ( is_numeric( $datetime ) ) {
			$dt = gmdate( 'Y-m-d\TH:i:s', $datetime );
		} elseif ( is_string( $datetime ) ) {
			$timestamp = strtotime( $datetime );
			if ( false === $timestamp ) {
				$dt = gmdate( 'Y-m-d\TH:i:s' );
			} else {
				$dt = gmdate( 'Y-m-d\TH:i:s', $timestamp );
			}
		} else {
			$dt = gmdate( 'Y-m-d\TH:i:s' );
		}

		return array(
			'dateTime' => $dt,
			'timeZone' => $timezone,
		);
	}
}
