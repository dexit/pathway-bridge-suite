<?php
/**
 * Class GSheets_Post_Bridge
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Google Sheets post bridge.
 */
class GSheets_Post_Bridge extends Post_Bridge {

	/**
	 * Handles spreadsheet rows data in memory.
	 *
	 * @var array
	 */
	private static $rows = null;

	/**
	 * Bridge constructor.
	 *
	 * @param array $data Bridge data.
	 */
	public function __construct( $data ) {
		parent::__construct( $data, 'gsheets' );
	}

	/**
	 * Format plain table values to named rows.
	 *
	 * @param array $values Matrix with table values.
	 *
	 * @return array
	 */
	private function values_to_rows( $values ) {
		$headers = $values[0] ?? array();

		$rows = array();

		array_splice( $values, 0, 1 );
		$l = count( $values );
		for ( $i = 1; $i <= $l; $i++ ) {
			$row = array();
			$m   = count( $headers );
			for ( $j = 0; $j < $m; $j++ ) {
				$row[ $headers[ $j ] ] = $values[ $i ][ $j ] ?? '';
			}

			$rows[] = $row;
		}

		return $rows;
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
			return new WP_Error( 'invalid_bridge', 'Bridge is invalid', (array) $this->data );
		}

		if ( null !== self::$rows && count( self::$rows ) ) {
			return array_keys( self::$rows[0] );
		}

		if ( ! $backend ) {
			$backend = $this->backend;
		}

		$endpoint = $this->endpoint( $this->single_endpoint ) . '!1:1';

		$response = $backend->get( $endpoint );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$values = $response['data']['values'];
		return $values[0] ?? array();
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
	 * Gets the formatted bridge endpoint.
	 *
	 * @param string|null $tab Tab name, optional.
	 *
	 * @return string
	 */
	protected function endpoint( $tab = null ) {
		$endpoint = $this->endpoint . '/values';

		if ( ! $tab ) {
			return $endpoint;
		}

		$tab = strtolower( strpos( trim( $tab ), ' ' ) ? "'{$tab}'" : $tab );
		return $endpoint . '/' . rawurlencode( $tab );
	}

	/**
	 * Fetches one row data by row ID.
	 *
	 * @param string $foreign_id ID of the target row.
	 * @param array  $params Request params.
	 * @param array  $headers HTTP headers.
	 *
	 * @return array|WP_Error
	 */
	public function fetch_one( $foreign_id, $params = array(), $headers = array() ) {
		$rows = $this->fetch_all( $params, $headers );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		foreach ( $rows as $row ) {
			$row_id = $row[ $this->foreign_key ] ?? null;
			if ( $row_id === $foreign_id ) {
				return $row;
			}
		}

		return new WP_Error( 'not_found', 'Record not found', array( 'foreign_id' => $foreign_id ) );
	}

	/**
	 * Fetches the spreadsheet table data.
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

		if ( null !== self::$rows ) {
			return self::$rows;
		}

		$backend = $this->backend;

		$sheets = $this->get_sheets( $backend );
		if ( is_wp_error( $sheets ) ) {
			return $sheets;
		}

		$tab = trim( $this->single_endpoint );
		if ( ! in_array( strtolower( $tab ), $sheets, true ) ) {
			$result = $this->add_sheet( count( $sheets ), $tab, $backend );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$endpoint = $this->endpoint( $this->single_endpoint );
		$response = $this->request( $endpoint, $params, $headers );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		self::$rows = $this->values_to_rows( $response['data']['values'] );
		return self::$rows;
	}
}
