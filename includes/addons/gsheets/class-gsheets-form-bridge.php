<?php
/**
 * Class GSheets_Form_Bridge
 *
 * @package formsbridge
 */

namespace FORMS_BRIDGE;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Form bridge implementation for the Google Sheets service.
 */
class GSheets_Form_Bridge extends Form_Bridge {

	/**
	 * Bridge constructor with addon name provisioning.
	 *
	 * @param array $data Bridge data.
	 */
	public function __construct( $data ) {
		parent::__construct( $data, 'gsheets' );
	}

	/**
	 * Fetches the first row of the sheet and return it as an array of headers / columns.
	 *
	 * @param Backend|null $backend Bridge backend instance.
	 *
	 * @return array<string>|WP_Error
	 */
	public function get_headers( $backend = null ) {
		if ( ! $this->is_valid ) {
			return new WP_Error( 'invalid_bridge' );
		}

		if ( ! $backend ) {
			$backend = $this->backend;
		}

		$tab   = strtolower( strpos( trim( $this->tab ), ' ' ) ? "'{$this->tab}'" : $this->tab );
		$range = rawurlencode( $tab ) . '!1:1';

		$response = $backend->get( $this->endpoint . '/values/' . $range );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['data']['values'][0] ?? array();
	}

	/**
	 * Creates a new sheet on the document.
	 *
	 * @param integer $index Position of the new sheet in the sheets list.
	 * @param string  $title Sheet title.
	 * @param Backend $backend Bridge backend instance.
	 *
	 * @return array|WP_Error Sheet data or creation error.
	 */
	private function add_sheet( $index, $title, $backend ) {
		$response = $backend->post(
			$this->endpoint . ':batchUpdate',
			array(
				'requests' => array(
					array(
						'addSheet' => array(
							'properties' => array(
								'sheetId'        => time(),
								'index'          => $index,
								'title'          => $title,
								'sheetType'      => 'GRID',
								'gridProperties' => array(
									'rowCount'    => 1000,
									'columnCount' => 26,
								),
								'hidden'         => false,
							),
						),
					),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['data'];
	}

	/**
	 * Request for the list of sheets of the document.
	 *
	 * @param Backend $backend Bridge backend instance.
	 *
	 * @return array<string>|WP_Error
	 */
	private function get_sheets( $backend ) {
		$response = $backend->get( $this->endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$sheets = array();
		foreach ( $response['data']['sheets'] as $sheet ) {
			$sheets[] = strtolower( $sheet['properties']['title'] );
		}

		return $sheets;
	}

	/**
	 * Sends the payload to the backend.
	 *
	 * @param array $payload Submission data.
	 * @param array $attachments Submission's attached files. Will be ignored.
	 *
	 * @return array|WP_Error Http request response.
	 */
	public function submit( $payload = array(), $attachments = array() ) {
		if ( ! $this->is_valid ) {
			return new WP_Error(
				'invalid_bridge',
				'Bridge data is invalid',
				(array) $this->data,
			);
		}

		$backend = $this->backend;
		if ( ! $backend ) {
			return new WP_Error( 'invalid_backend', 'Backend not found' );
		}

		$sheets = $this->get_sheets( $backend );
		if ( is_wp_error( $sheets ) ) {
			return $sheets;
		}

		$tab = trim( $this->tab );
		if ( ! in_array( strtolower( $tab ), $sheets, true ) ) {
			$result = $this->add_sheet( count( $sheets ), $tab, $backend );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$tab      = strtolower( strpos( $tab, ' ' ) ? "'{$tab}'" : $tab );
		$endpoint = $this->endpoint . '/values/' . rawurlencode( $tab );
		$method   = $this->method;

		if ( 'POST' === $method || 'PUT' === $method ) {
			$endpoint .= '!A1:Z:append/?valueInputOption=USER_ENTERED';

			$headers = $this->get_headers( $backend );
			if ( is_wp_error( $headers ) ) {
				return $headers;
			}

			$payload = self::flatten_payload( $payload );
			$values  = array();

			if ( empty( $headers ) ) {
				$headers  = array_keys( $payload );
				$values[] = $headers;
			}

			$row = array();
			foreach ( $headers as $header ) {
				if ( isset( $payload[ $header ] ) ) {
					$row[] = $payload[ $header ] ?? '';
				} else {
					$row[] = '';
				}
			}

			$values[] = $row;

			$payload = array(
				// 'range' => $this->value_range($values),
				'majorDimension' => 'ROWS',
				'values'         => $values,
			);
		}

		return $this->backend->$method( $endpoint, $payload );
	}

	/**
	 * Sheets are flat, if payload has nested arrays, flattens it and concatenate its keys
	 * as field names.
	 *
	 * @param array  $payload Submission payload.
	 * @param string $path Prefix to prepend to the field name.
	 *
	 * @return array Flattened payload.
	 */
	private static function flatten_payload( $payload, $path = '' ) {
		$flat = array();
		foreach ( $payload as $field => $value ) {
			$key   = $path . $field;
			$value = self::flatten_value( $value, $key );

			if ( ! is_array( $value ) ) {
				$flat[ $key ] = $value;
			} else {
				foreach ( $value as $_key => $_val ) {
					$flat[ $_key ] = $_val;
				}
			}
		}

		return $flat;
	}

	/**
	 * Returns array values as a flat vector of play key values.
	 *
	 * @param mixed  $value Payload value.
	 * @param string $path Hierarchical path to the value.
	 *
	 * @return mixed
	 */
	private static function flatten_value( $value, $path = '' ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( wp_is_numeric_array( $value ) ) {
			$simple_items = array_filter( $value, fn( $item ) => ! is_array( $item ) );

			if ( count( $simple_items ) === count( $value ) ) {
				return implode( ',', $value );
			}
		}

		return self::flatten_payload( $value, $path . '.' );
	}
}
