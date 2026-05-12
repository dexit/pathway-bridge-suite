<?php
/**
 * Class Nextcloud_Post_Bridge
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use PBAPI;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Post bridge implementation for the Nextcloud JSON-RPC api.
 */
class Nextcloud_Post_Bridge extends Post_Bridge {

	/**
	 * Handles csv rows data in memory.
	 *
	 * @var array|null
	 */
	private static $rows = null;

	/**
	 * Bridge constructor with addon name provisioning.
	 *
	 * @param array $data Bridge data.
	 */
	public function __construct( $data ) {
		parent::__construct( $data, 'nextcloud' );
	}

	/**
	 * Downloads the file from nextcloud and stream its contents to the bridge filepath.
	 *
	 * @param Backend|null $backend Backend object.
	 *
	 * @return string|WP_Error Filepath or error.
	 */
	private function download_file( $backend ) {
		$filepath = $this->filepath();

		$response = $backend->get(
			rawurlencode( $this->endpoint ),
			array(),
			array(),
			array(
				'stream'   => true,
				'filename' => $filepath,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( is_file( $filepath ) ) {
				wp_delete_file( $filepath );
			}

			return $response;
		}

		$mime_type = mime_content_type( $filepath );
		if ( 'text/csv' !== $mime_type ) {
			wp_delete_file( $filepath );
			return new WP_Error( 'mimetype_error', 'File is not CSV', array( 'filepath' => $filepath ) );
		}

		return $filepath;
	}

	/**
	 * Returns the bridge local backup file path.
	 *
	 * @param bool &$touched Pointer to handle if the file has been touched boolean value.
	 *
	 * @return string|WP_Error File path or WP_Error if no write permissions.
	 */
	private function filepath( &$touched = false ) {
		$uploads = Posts_Bridge::upload_dir() . '/nextcloud';

		if ( ! is_dir( $uploads ) ) {
			if ( ! wp_mkdir_p( $uploads, 755 ) ) {
				return new WP_Error(
					'file_permission_error',
					'Can not create the uploads directory',
					array( 'directory' => $uploads ),
				);
			}
		}

		$endpoint = ltrim( $this->data['endpoint'], '/' );
		$name     = str_replace( '/', '-', $endpoint );
		$filepath = $uploads . '/' . $name;

		if ( ! str_ends_with( strtolower( $filepath ), '.csv' ) ) {
			$filepath .= '.csv';
		}

		if ( ! is_file( $filepath ) ) {
			// phpcs:disable WordPress.WP.AlternativeFunctions
			$result = touch( $filepath );
			// phpcs:enable

			if ( ! $result ) {
				return new WP_Error(
					'file_permission_error',
					'Can not create the local file',
					array( 'filepath' => $filepath ),
				);
			}

			$touched = true;
		} else {
			$touched = false;
		}

		return $filepath;
	}

	/**
	 * Returns the bridge table headers.
	 *
	 * @return array
	 */
	public function table_headers() {
		if ( ! $this->is_valid ) {
			return new WP_Error( 'invalid_bridge' );
		}

		$backend = $this->backend();
		if ( ! $backend ) {
			return new WP_Error( 'invalid_backend' );
		}

		$filepath = $this->filepath( $touched );

		if ( is_wp_error( $filepath ) ) {
			return $filepath;
		}

		$dav_modified = $touched ? time() + 3600 : $this->get_dav_modified_date( $backend );
		if ( is_wp_error( $dav_modified ) ) {
			return $dav_modified;
		}

		if ( $touched || filemtime( $filepath ) < $dav_modified ) {
			$filepath = $this->download_file( $backend );

			if ( is_wp_error( $filepath ) ) {
				return $filepath;
			}
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions
		$stream = fopen( $filepath, 'r' );
		$line   = fgets( $stream );
		fclose( $stream );
		// phpcs:enable

		if ( false === $line ) {
			return array();
		}

		return $this->decode_row( $line );
	}

	/**
	 * Returns the remote file modification date.
	 *
	 * @param Backend $backend Bridge backend instance.
	 *
	 * @return integer|null
	 */
	private function get_dav_modified_date( $backend ) {
		if ( ! $backend ) {
			$backend = $this->backend;
		}

		$response = $backend->head( rawurlencode( $this->endpoint ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$last_modified = $response['headers']['last-modified'] ?? null;

		if ( ! $last_modified ) {
			return new WP_Error( 'bad_response', 'Webdav Last-Modified header not found', $response );
		}

		return strtotime( $last_modified );
	}

	/**
	 * Returns the content of the local file as named rows.
	 *
	 * @param string $filepath Path to the file.
	 *
	 * @return array
	 */
	private function read_rows( $filepath ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions
		$content = file_get_contents( $filepath );
		// phpcs:enable

		$bom     = pack( 'H*', 'EFBBBF' );
		$content = preg_replace( "/^$bom/", '', trim( $content ) );

		$lines = explode( "\n", $content );
		$rows  = array();

		$columns = array_splice( $lines, 0, 1 );
		$headers = $this->decode_row( $columns[0] ?? '' );

		if ( empty( $headers ) ) {
			return $rows;
		}

		foreach ( $lines as $line ) {
			$values = $this->decode_row( $line );
			$row    = array();

			$l = count( $headers );
			for ( $i = 0; $i < $l; ++$i ) {
				$name  = $headers[ $i ] ?? '';
				$value = $values[ $i ] ?? '';

				if ( ! $name ) {
					continue;
				}

				$row[ $name ] = $value;
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Returns a csv row as a list of values.
	 *
	 * @param string $row Comma separated values string.
	 *
	 * @return array
	 */
	private function decode_row( $row ) {
		$row = preg_replace( '/\n+/', '', $row );
		return array_map(
			function ( $value ) {
				$value = trim( $value );
				if ( ! $value ) {
					return $value;
				}

				$decoded = json_decode( $value );
				if ( $decoded ) {
					return $decoded;
				}

				return $value;
			},
			explode( ',', $row )
		);
	}

	/**
	 * Lookups in the bridge rows for a match with the value of the foreign_id.
	 *
	 * @param int|string $foreign_id Foreig key value.
	 * @param array      $params Ignored.
	 * @param array      $headers HTTP headers.
	 *
	 * @return array|WP_Error Remote data for the given id.
	 */
	public function fetch_one( $foreign_id, $params = array(), $headers = array() ) {
		$rows = $this->fetch_all( $params, $headers );

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		foreach ( $rows as $row ) {
			$key = $this->foreign_key;
			if ( isset( $row[ $key ] ) && $foreign_id === $row[ $key ] ) {
				return $row;
			}
		}

		return new WP_Error( 'not_found', 'Row not found', array( 'foreign_id' => $foreign_id ) );
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
		if ( null !== self::$rows ) {
			return self::$rows;
		}

		$filepath = $this->filepath( $touched );

		$params['_touched'] = $touched;

		$rows = $this->request( $filepath, $params, $headers );

		if ( is_wp_error( $rows ) ) {
			return array();
		}

		return $rows;
	}
	/**
	 * Peforms a request to a filepath
	 *
	 * @param string $filepath Foo.
	 * @param array  $params Submission data.
	 * @param array  $headers Submission attachments.
	 *
	 * @return array|WP_Error Http
	 */
	public function request( $filepath, $params = array(), $headers = array() ) {
		if ( ! $this->is_valid ) {
			return new WP_Error(
				'invalid_bridge',
				'Bridge data is invalid',
				(array) $this->data,
			);
		}

		$backend = $this->backend;

		if ( ! $backend ) {
			return new WP_Error(
				'invalid_backend',
				'Bridge has no valid backend',
				(array) $this->data,
			);
		}

		if ( isset( $params['_touched'] ) ) {
			$touched = $params['_touched'];
			unset( $params['_touched'] );
		} else {
			$filepath = $this->filepath( $touched );
		}

		$dav_modified = $touched ? time() + 3600 : $this->get_dav_modified_date( $backend );
		if ( is_wp_error( $dav_modified ) ) {
			return $dav_modified;
		}

		if ( $touched || filemtime( $filepath ) < $dav_modified ) {
			$filepath = $this->download_file( $backend );

			if ( is_wp_error( $filepath ) ) {
				return $filepath;
			}
		}

		$rows = $this->read_rows( $filepath );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		self::$rows = $rows;
		return self::$rows;
	}

	/**
	 * Retrives the bridge's backend instance with the base url formated to point
	 * to the root of the nextcloud webdav API.
	 *
	 * @return Backend|null
	 */
	protected function backend() {
		if ( ! $this->is_valid ) {
			return;
		}

		$backend = PBAPI::get_backend( $this->data['backend'] );
		if ( ! $backend ) {
			return;
		}

		$base_url = $backend->base_url;
		$base_url = substr( $base_url, 0, strpos( $base_url, '/remote.php', 0 ) ?: strlen( $base_url ) );

		$credential = $backend->credential;
		if ( ! $credential || 'Basic' !== $credential->schema ) {
			return;
		}

		$user     = rawurlencode( $credential->client_id );
		$base_url = rtrim( $base_url, '/' ) . "/remote.php/dav/files/{$user}/";

		return $backend->clone( array( 'base_url' => $base_url ) );
	}
}
