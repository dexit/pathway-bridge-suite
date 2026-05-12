<?php
/**
 * Class Grist_Form_Bridge
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Post bridge implementation for the Grist service.
 */
class Grist_Post_Bridge extends Post_Bridge {

	/**
	 * Handles spreadsheet columns data in memory.
	 *
	 * @var array
	 */
	private static $columns = null;

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
		parent::__construct( $data, 'grist' );
	}

	/**
	 * Gets the document id from the bridge endpoint.
	 *
	 * @return string|null
	 */
	private function doc_id() {
		preg_match( '/(?<=docs\/)[^\/]+/', $this->endpoint, $matches );

		if ( empty( $matches[0] ) ) {
			return null;
		}

		return $matches[0];
	}

	/**
	 * Gets the table id from the bridge endpoint.
	 *
	 * @return string|null
	 */
	private function table_id() {
		preg_match( '/(?<=tables\/)[^\/]+/', $this->endpoint, $matches );

		if ( empty( $matches[0] ) ) {
			return null;
		}

		return $matches[0];
	}

	/**
	 * Fetches the fields of the Grist table and returns them as an array.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public function get_fields() {
		if ( ! $this->is_valid ) {
			return new WP_Error( 'invalid_bridge', 'The bridge is invalid', $this->data );
		}

		if ( null === self::$columns ) {
			$backend = $this->backend;
			if ( ! $backend ) {
				return new WP_Error( 'invalid_backend', 'The bridge backend is unkown or invalid', $this->data );
			}

			$doc_id   = $this->doc_id();
			$table_id = $this->table_id();

			if ( ! $doc_id || ! $table_id ) {
				return new WP_Error( 'invalid_endpoint', 'The bridge has an invalid endpoint', $this->data );
			}

			$endpoint = "/api/docs/{$doc_id}/tables/{$table_id}/columns";
			$response = $backend->get( $endpoint );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			self::$columns = $response['data']['columns'];
		}

		$fields = array();
		foreach ( self::$columns as $column ) {
			$field = array(
				'name'   => $column['id'],
				'schema' => array(),
			);

			$type = explode( ':', $column['fields']['type'] )[0];
			switch ( $type ) {
				case 'Attachments':
					$field['schema'] = array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					);
					break;
				case 'RefList':
				case 'ChoiceList':
					$field['schema'] = array(
						'type'  => 'string',
						'items' => array( 'type' => 'string' ),
					);
					break;
				case 'Bool':
					$field['schema']['type'] = 'boolean';
					break;
				case 'Ref':
				case 'Int':
					$field['schema']['type'] = 'integer';
					break;
				case 'Numeric':
					$field['schema']['type'] = 'number';
					break;
				case 'Any':
					$field['schema']['type'] = 'mixed';
					break;
				case 'Date':
				case 'Choice':
				default:
					$field['schema']['type'] = 'text';
					break;
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Performs a request to the bridge single_endpoint using the bridge backend and HTTP method.
	 *
	 * @param int|string $foreign_id Foreig key value.
	 * @param array      $params Ignored.
	 * @param array      $headers HTTP headers.
	 *
	 * @return array|WP_Error Remote data for the given id.
	 */
	public function fetch_one( $foreign_id, $params = array(), $headers = array() ) {
		$fields = $this->get_fields();
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$records = $this->fetch_all( $params, $headers );
		if ( is_wp_error( $records ) ) {
			return $records;
		}

		foreach ( $records as $record ) {
			if ( $record['id'] === $foreign_id ) {
				foreach ( $record['fields'] as $key => &$value ) {
					if ( is_array( $value ) && 'L' === $value[0] ) {
						array_shift( $value );
					}
				}

				$record = $this->attachments_as_urls( $record );
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
			return new WP_Error( 'invalid_bridge', 'Bridge data is invalid', (array) $this->data );
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

	/**
	 * Replace attachment ids by its corresponding download URL in a record.
	 *
	 * @param array $record Record data.
	 *
	 * @return array
	 */
	private function attachments_as_urls( $record ) {
		static $attachments;

		if ( ! is_array( $attachments ) ) {
			$attachments = array();

			foreach ( self::$columns as $column ) {
				if ( 'Attachments' === $column['fields']['type'] ) {
					$attachments[] = $column['id'];
				}
			}
		}

		if ( empty( $attachments ) ) {
			return $record;
		}

		$backend = $this->backend;
		$doc_id  = $this->doc_id();

		foreach ( $record['fields'] as $name => &$value ) {
			if ( in_array( $name, $attachments, true ) && is_array( $value ) ) {
				$l = count( $value );
				for ( $i = 0; $i < $l; ++$i ) {
					$value[ $i ] = $backend->url( "/api/docs/{$doc_id}/attachments/{$value[ $i ]}/download" );
				}
			}
		}

		return $record;
	}
}
