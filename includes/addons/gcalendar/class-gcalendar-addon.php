<?php
/**
 * Class GCalendar_Addon
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use PBAPI;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once 'class-gcalendar-post-bridge.php';
require_once 'hooks.php';

/**
 * Google Calendar addon class.
 */
class GCalendar_Addon extends Addon {

	/**
	 * Handles the addon's title.
	 *
	 * @var string
	 */
	const TITLE = 'Google Calendar';

	/**
	 * Handles the addon's name.
	 *
	 * @var string
	 */
	const NAME = 'gcalendar';

	/**
	 * Handles the addon's custom bridge class.
	 *
	 * @var string
	 */
	const BRIDGE = '\POSTS_BRIDGE\GCalendar_Post_Bridge';

	/**
	 * Performs a request against the backend to check the connexion status.
	 *
	 * @param string $backend Backend name.
	 *
	 * @return boolean
	 */
	public function ping( $backend ) {
		$bridge = new GCalendar_Post_Bridge(
			array(
				'backend'  => $backend,
				'endpoint' => '/calendar/v3/users/me/calendarList',
				'method'   => 'GET',
			)
		);

		$backend = $bridge->backend;
		if ( ! $backend ) {
			Logger::log( 'Google Calendar backend ping error: Bridge has no valid backend', Logger::ERROR );
			return false;
		}

		$credential = $backend->credential;
		if ( ! $credential ) {
			Logger::log( 'Google Calendar backend ping error: Backend has no valid credential', Logger::ERROR );
			return false;
		}

		$parsed = wp_parse_url( $backend->base_url );
		$host   = $parsed['host'] ?? '';

		if ( 'www.googleapis.com' !== $host ) {
			Logger::log( 'Google Calendar backend ping error: Backend does not point to the Google Calendar API endpoints', Logger::ERROR );
			return false;
		}

		$access_token = $credential->get_access_token();

		if ( ! $access_token ) {
			Logger::log( 'Google Calendar backend ping error: Unable to recover the credential access token', Logger::ERROR );
			return false;
		}

		return true;
	}

	/**
	 * Performs a GET request against the backend endpoint and retrieve the response data.
	 *
	 * @param string $endpoint Calendar ID or endpoint.
	 * @param string $backend Backend name.
	 *
	 * @return array|WP_Error
	 */
	public function fetch( $endpoint, $backend ) {
		$backend = PBAPI::get_backend( $backend );
		if ( ! $backend ) {
			return new WP_Error( 'invalid_backend' );
		}

		$credential = $backend->credential;
		if ( ! $credential ) {
			return new WP_Error( 'invalid_credential' );
		}

		$access_token = $credential->get_access_token();
		if ( ! $access_token ) {
			return new WP_Error( 'invalid_credential' );
		}

		$response = http_bridge_get(
			'https://www.googleapis.com/calendar/v3/users/me/calendarList',
			array(),
			array(
				'Authorization' => "Bearer {$access_token}",
				'Accept'        => 'application/json',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Performs an introspection of the backend API and returns a list of available endpoints.
	 *
	 * @param string      $backend Target backend name.
	 * @param string|null $method HTTP method.
	 *
	 * @return array|WP_Error
	 */
	public function get_endpoints( $backend, $method = null ) {
		$response = $this->fetch( null, $backend );

		if ( is_wp_error( $response ) || empty( $response['data']['items'] ) ) {
			Logger::log( 'Google Calendar get endpoints error: Introspection error response', Logger::ERROR );
			Logger::log( $response, Logger::ERROR );
			return array();
		}

		return array_map(
			function ( $calendar ) {
				return '/calendar/v3/calendars/' . $calendar['id'] . '/events';
			},
			$response['data']['items']
		);
	}

	/**
	 * Performs an introspection of the backend endpoint and returns API fields
	 * and accepted content type.
	 *
	 * @param string      $endpoint Calendar ID.
	 * @param string      $backend Backend name.
	 * @param string|null $method HTTP method.
	 *
	 * @return array List of fields and content type of the endpoint.
	 */
	public function get_endpoint_schema( $endpoint, $backend, $method = null ) {
		$schema = array(
			'kind'                      => array( 'type' => 'string' ),
			'id'                        => array( 'type' => 'string' ),
			'status'                    => array( 'type' => 'string' ),
			'htmlLink'                  => array( 'type' => 'string' ),
			'created'                   => array( 'type' => 'string' ),
			'updated'                   => array( 'type' => 'string' ),
			'summary'                   => array( 'type' => 'string' ),
			'description'               => array( 'type' => 'string' ),
			'location'                  => array( 'type' => 'string' ),
			'colorId'                   => array( 'type' => 'string' ),
			'creator'                   => array(
				'type'       => 'object',
				'properties' => array(
					'id'          => array( 'type' => 'string' ),
					'email'       => array( 'type' => 'string' ),
					'displayName' => array( 'type' => 'string' ),
					'self'        => array( 'type' => 'boolean' ),
				),
			),
			'organizer'                 => array(
				'type'       => 'object',
				'properties' => array(
					'id'          => array( 'type' => 'string' ),
					'email'       => array( 'type' => 'string' ),
					'displayName' => array( 'type' => 'string' ),
					'self'        => array( 'type' => 'boolean' ),
				),
			),
			'start'                     => array(
				'type'       => 'object',
				'properties' => array(
					'date'     => array( 'type' => 'string' ),
					'dateTime' => array( 'type' => 'string' ),
					'timeZone' => array( 'type' => 'string' ),
				),
			),
			'end'                       => array(
				'date'     => array( 'type' => 'string' ),
				'dateTime' => array( 'type' => 'string' ),
				'timeZone' => array( 'type' => 'string' ),
			),
			'endTimeUnspecified'        => array( 'type' => 'boolean' ),
			'recurrence'                => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'recurringEventId'          => array( 'type' => 'string' ),
			'originalStartTime'         => array(
				'type'       => 'object',
				'properties' => array(
					'date'     => array( 'type' => 'string' ),
					'dateTime' => array( 'type' => 'string' ),
					'timeZone' => array( 'type' => 'string' ),
				),
			),
			'transparency'              => array( 'type' => 'string' ),
			'visibility'                => array( 'type' => 'string' ),
			'iCalUID'                   => array( 'type' => 'string' ),
			'sequence'                  => array( 'type' => 'integer' ),
			'attendees'                 => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'               => array( 'type' => 'string' ),
						'email'            => array( 'type' => 'string' ),
						'displayName'      => array( 'type' => 'string' ),
						'organizer'        => array( 'type' => 'boolean' ),
						'self'             => array( 'type' => 'boolean' ),
						'resource'         => array( 'type' => 'boolean' ),
						'optional'         => array( 'type' => 'boolean' ),
						'responseStatus'   => array( 'type' => 'string' ),
						'comment'          => array( 'type' => 'string' ),
						'additionalGuests' => array( 'type' => 'integer' ),
					),
				),
			),
			'attendeesOmitted'          => array( 'type' => 'boolean' ),
			'extendedProperties'        => array(
				'type'       => 'object',
				'properties' => array(
					'private' => array(
						'type'       => 'object',
						'properties' => array(),
					),
					'shared'  => array(
						'type'       => 'object',
						'properties' => array(),
					),
				),
			),
			'hangoutLink'               => array( 'type' => 'string' ),
			'conferenceData'            => array(
				'type'       => 'object',
				'properties' => array(
					'createRequest'      => array(
						'type'       => 'object',
						'properties' => array(
							'requestId'             => array( 'type' => 'string' ),
							'conferenceSolutionKey' => array( 'type' => array( 'type' => 'string' ) ),
							'status'                => array(
								'type'       => 'object',
								'properties' => array(
									'statusCode' => array( 'type' => 'string' ),
								),
							),
						),
					),
					'entryPoints'        => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'entryPointType' => array( 'type' => 'string' ),
								'uri'            => array( 'type' => 'string' ),
								'label'          => array( 'type' => 'string' ),
								'pin'            => array( 'type' => 'string' ),
								'accessCode'     => array( 'type' => 'string' ),
								'meetingCode'    => array( 'type' => 'string' ),
								'passcode'       => array( 'type' => 'string' ),
								'password'       => array( 'type' => 'string' ),
							),
						),
					),
					'conferenceSolution' => array(
						'type'       => 'object',
						'properties' => array(
							'key'     => array( 'type' => array( 'type' => 'string' ) ),
							'name'    => array( 'type' => 'string' ),
							'iconUri' => array( 'type' => 'string' ),
						),
					),
					'conferenceId'       => array( 'type' => 'string' ),
					'signature'          => array( 'type' => 'string' ),
					'notes'              => array( 'type' => 'string' ),
				),
			),
			'gadget'                    => array(
				'type'       => 'object',
				'properties' => array(
					'type'        => array( 'type' => 'string' ),
					'title'       => array( 'type' => 'string' ),
					'link'        => array( 'type' => 'string' ),
					'iconLink'    => array( 'type' => 'string' ),
					'width'       => array( 'type' => 'integer' ),
					'height'      => array( 'type' => 'integer' ),
					'display'     => array( 'type' => 'string' ),
					'preferences' => array(
						'type'       => 'object',
						'properties' => array(),
					),
				),
			),
			'anyoneCanAddSelf'          => array( 'type' => 'boolean' ),
			'guestsCanInviteOthers'     => array( 'type' => 'boolean' ),
			'guestsCanModify'           => array( 'type' => 'boolean' ),
			'guestsCanSeeOtherGuests'   => array( 'type' => 'boolean' ),
			'privateCopy'               => array( 'type' => 'boolean' ),
			'locked'                    => array( 'type' => 'boolean' ),
			'reminders'                 => array(
				'type'       => 'object',
				'properties' => array(
					'useDefault' => array( 'type' => 'boolean' ),
					'overrides'  => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'method'  => array( 'type' => 'string' ),
								'minutes' => array( 'type' => 'integer' ),
							),
						),
					),
				),
			),
			'source'                    => array(
				'url'   => array( 'type' => 'string' ),
				'title' => array( 'type' => 'string' ),
			),
			'workingLocationProperties' => array(
				'type'       => 'object',
				'properties' => array(
					'type'           => array( 'type' => 'string' ),
					'homeOffice'     => array( 'type' => 'string' ),
					'customLocation' => array(
						'type'       => 'object',
						'properties' => array( 'label' => array( 'type' => 'string' ) ),
					),
					'officeLocation' => array(
						'type'       => 'object',
						'properties' => array(
							'buildingId'     => array( 'type' => 'string' ),
							'floorId'        => array( 'type' => 'string' ),
							'floorSectionId' => array( 'type' => 'string' ),
							'deskId'         => array( 'type' => 'string' ),
							'label'          => array( 'type' => 'string' ),
						),
					),
				),
			),
			'outOfOfficeProperties'     => array(
				'type'       => 'object',
				'properties' => array(
					'autoDeclineMode' => array( 'type' => 'string' ),
					'declineMessage'  => array( 'type' => 'string' ),
				),
			),
			'focusTimeProperties'       => array(
				'type'       => 'object',
				'properties' => array(
					'autoDeclineMode' => array( 'type' => 'string' ),
					'declineMessage'  => array( 'type' => 'string' ),
					'chatStatus'      => array( 'type' => 'string' ),
				),
			),
			'attachments'               => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'fileUrl'  => array( 'type' => 'string' ),
						'title'    => array( 'type' => 'string' ),
						'mimeType' => array( 'type' => 'string' ),
						'iconLink' => array( 'type' => 'string' ),
						'fileId'   => array( 'type' => 'string' ),
					),
				),
			),
			'birthdayProperties'        => array(
				'type'       => 'object',
				'properties' => array(
					'contact'        => array( 'type' => 'string' ),
					'type'           => array( 'type' => 'string' ),
					'customTypeName' => array( 'type' => 'string' ),
				),
			),
			'eventType'                 => array( 'type' => 'string' ),
		);

		$fields = array();
		foreach ( $schema as $name => $field_schema ) {
			$fields[] = array(
				'name'   => $name,
				'schema' => $field_schema,
			);
		}

		return OpenAPI::expand_fields_schema( $fields );
	}

	/**
	 * Gets expiration time for introspection cache based on the introspection
	 * method.
	 *
	 * @param string $method Introspection method (ping, endpoints, schema).
	 *
	 * @return int Time in seconds.
	 */
	public function introspection_cache_expiration( $method ) {
		if ( Logger::is_active() ) {
			return 0;
		}

		switch ( $method ) {
			case 'ping':
				return 60 * 10;
			case 'endpoints':
				return 60 * 5;
			default:
				return 0;
		}
	}
}

GCalendar_Addon::setup();
