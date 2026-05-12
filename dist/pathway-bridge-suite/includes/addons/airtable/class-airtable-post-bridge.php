<?php
/**
 * Class Airtable_Post_Bridge
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Form bridge implementation for the Airtable service.
 */
class Airtable_Post_Bridge extends Post_Bridge {

	/**
	 * Handles spreadsheet records data in memory.
	 *
	 * @var array
	 */
	private static $records = null;

	/**
	 * Bridge constructor with addon name provisioning.
	 *
	 * @param array $data Bridge data.
	 */
	public function __construct( $data ) {
		$data['foreign_id'] = 'id';

		parent::__construct( $data, 'airtable' );
	}

	/**
	 * Gets the base id from the bridge endpoint.
	 *
	 * @return string|null
	 */
	private function base_id() {
		preg_match( '/\/v\d+\/([^\/]+)\/([^\/]+)/', $this->endpoint, $matches );

		if ( 3 !== count( $matches ) ) {
			return null;
		}

		return $matches[1];
	}

	/**
	 * Gets the table id from the bridge endpoint.
	 *
	 * @return string|null
	 */
	private function table_id() {
		preg_match( '/\/v\d+\/([^\/]+)\/([^\/]+)/', $this->endpoint, $matches );

		if ( 3 !== count( $matches ) ) {
			return null;
		}

		return explode( '/', $matches[2] )[0];
	}

	/**
	 * Fetches the fields of the Airtable table and returns them as an array.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public function get_fields() {
		if ( ! $this->is_valid ) {
			return new WP_Error( 'invalid_bridge', 'The bridge is invalid', $this->data );
		}

		$backend = $this->backend;
		if ( ! $backend ) {
			return new WP_Error( 'invalid_backend', 'The bridge backend is unkown or invalid', $this->data );
		}

		$base_id  = $this->base_id();
		$table_id = $this->table_id();

		if ( ! $base_id || ! $table_id ) {
			return new WP_Error( 'invalid_endpoint', 'The bridge has an invalid endpoint', $this->data );
		}

		$endpoint = "/v0/meta/bases/{$base_id}/tables";
		$response = $backend->get( $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		foreach ( $response['data']['tables'] as $candidate ) {
			if ( $table_id === $candidate['id'] || $table_id === $candidate['name'] ) {
				$table = $candidate;
				break;
			}
		}

		if ( ! isset( $table ) ) {
			return new WP_Error( 'not_found', 'Table not found', $this->data );
		}

		$fields = array();
		foreach ( $table['fields'] as $air_field ) {
			if (
				in_array(
					$air_field['type'],
					array(
						'button',
						'externalSyncSource',
						'multipleCollaborators',
						'multipleRecordLinks',
					),
					true,
				)
			) {
				continue;
			}

			$field = array(
				'$id'    => $air_field['id'],
				'name'   => $air_field['name'],
				'schema' => array(),
			);

			switch ( $air_field['type'] ) {
				case 'aiText':
					$field['schema'] = array(
						'type'       => 'object',
						'properties' => array(
							'state'     => array( 'type' => 'string' ),
							'errorType' => array( 'type' => 'string' ),
							'value'     => array( 'type' => 'string' ),
							'isStale'   => array( 'type' => 'boolean' ),
						),
					);
					break;
				case 'multipleAttachments':
					$field['schema'] = array(
						'type'            => 'array',
						'items'           => array(
							'type'                 => 'object',
							'properties'           => array(
								'id'         => array( 'type' => 'string' ),
								'width'      => array( 'type' => 'number' ),
								'height'     => array( 'type' => 'number' ),
								'url'        => array( 'type' => 'string' ),
								'filename'   => array( 'type' => 'string' ),
								'type'       => array( 'type' => 'number' ),
								'thumbnails' => array(
									'type'       => 'object',
									'properties' => array(
										'small' => array(
											'type'       => 'object',
											'properties' => array(
												'url'    => array( 'type' => 'string' ),
												'width'  => array( 'type' => 'number' ),
												'height' => array( 'type' => 'number' ),
											),
										),
										'large' => array(
											'type'       => 'object',
											'properties' => array(
												'url'    => array( 'type' => 'string' ),
												'width'  => array( 'type' => 'number' ),
												'height' => array( 'type' => 'number' ),
											),
										),
										'full'  => array(
											'type'       => 'object',
											'properties' => array(
												'url'    => array( 'type' => 'string' ),
												'width'  => array( 'type' => 'number' ),
												'height' => array( 'type' => 'number' ),
											),
										),
									),
								),
							),
							'additionalProperties' => false,
						),
						'additionalItems' => true,
					);
					break;
				case 'rating':
				case 'number':
				case 'count':
					$field['schema']['type'] = 'number';
					break;
				case 'checkbox':
					$field['schema']['type'] = 'boolean';
					break;
				case 'formula':
				case 'multipleLookupValues':
				case 'rollup':
					$type = $air_field['options']['result']['type'] ?? 'string';
					if ( 'number' !== $type ) {
						$type = 'string';
					}

					$field['schema']['type'] = $type;
					break;
				case 'multipleSelects':
					$field['schema']['type']  = 'array';
					$field['schema']['items'] = array( 'type' => 'string' );
					break;
				case 'date':
				case 'multilineText':
				case 'singleSelect':
				default:
					$field['schema']['type'] = 'string';
					break;
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Lookups in the bridge records for a match with the value of the foreign_id.
	 *
	 * @param int|string $foreign_id Foreig key value.
	 * @param array      $params Ignored.
	 * @param array      $headers HTTP headers.
	 *
	 * @return array|WP_Error Remote data for the given id.
	 */
	public function fetch_one( $foreign_id, $params = array(), $headers = array() ) {
		$records = $this->fetch_all( $params, $headers );
		if ( is_wp_error( $records ) ) {
			return $records;
		}

		foreach ( $records as $record ) {
			if ( $record['id'] === $foreign_id ) {
				return $record['fields'];
			}
		}

		return new WP_Error( 'not_found', 'Record not found', array( 'foreign_id' => $foreign_id ) );
	}

	/**
	 * Performs a request to the bridge endpoint using the bridge backend and HTTP method.
	 *
	 * @param array $params Request params.
	 * @param array $headers HTTP headers.
	 *
	 * @return array|WP_Error Backend entries data.
	 */
	public function fetch_all( $params = array(), $headers = array() ) {
		if ( ! $this->is_valid ) {
			return new WP_Error( 'invalid_bridge', 'Bridge is invalid', (array) $this->data );
		}

		if ( null !== self::$records ) {
			return self::$records;
		}

		$response = $this->request( $this->endpoint, $params, $headers );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		self::$records = $response['data']['records'];
		return self::$records;
	}
}
