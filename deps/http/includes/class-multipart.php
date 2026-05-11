<?php
/**
 * Class Multipart
 *
 * @package httpbridge
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

namespace HTTP_BRIDGE;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Multipart data encoder.
 */
class Multipart {

	/**
	 * End of line handler.
	 *
	 * @var string EOL end of line chars.
	 */
	public const EOL = "\r\n";

	private const EOL_RE = "(?:\n|\r|\t)";

	/**
	 * Encoded data handler.
	 *
	 * @var string $data encoded data.
	 */
	private $data = '';

	/**
	 * Mime boundary handler.
	 *
	 * @var string $mime_boundary Unique part ID.
	 */
	private $mime_boundary;

	/**
	 * Returns a multipart object from an array of data.
	 *
	 * @param array       $data Payload.
	 * @param string|null $boundary Optiona, multipart boundary.
	 *
	 * @return Multipart|null
	 */
	public static function from( $data, $boundary = null ) {
		try {
			return new Multipart( $data, $boundary );
		} catch ( Exception ) {
			return null;
		}
	}

	/**
	 * Creates a random mime boundary.
	 *
	 * @param array|null  $data Payload data.
	 * @param string|null $boundary Multipart fields boundary.
	 */
	public function __construct( $data = null, $boundary = null ) {
		if ( is_string( $data ) ) {
			$this->set_data( $data, $boundary );
		} else {
			$this->mime_boundary = 'HttpBridge' . md5( microtime( true ) );
		}
	}

	/**
	 * Add part header boundary
	 */
	private function add_part_header() {
		$this->data .= '--------' . $this->mime_boundary . self::EOL;
	}

	/**
	 * Multipart serializer object data setter.
	 *
	 * @param string $data Serialized payload data.
	 * @param string $boundary Multipart fields boundary.
	 *
	 * @return Multipart
	 *
	 * @throws Exception If boundary not found on the data string.
	 */
	private function set_data( $data, $boundary = null ) {
		$this->data .= $data . self::EOL;
		if ( $boundary ) {
			if (
				preg_match(
					'/^' .
						self::EOL_RE .
						'*--+' .
						$boundary .
						'' .
						self::EOL_RE .
						'+/',
					$data
				)
			) {
				$this->mime_boundary = $boundary;
			} else {
				throw new Exception( 'Invalid multipart/form-data boundary' );
			}
		} elseif (
				preg_match(
					'/^' . self::EOL_RE . '*--+(.*)' . self::EOL_RE . '+/',
					$data,
					$match
				)
			) {
				$this->mime_boundary = $match[1];
		} else {
			throw new Exception( 'Invalid multipart/form-data payload' );
		}

		return $this;
	}

	/**
	 * Encode array data as multipart text.
	 *
	 * @param array<string|int>, mixed> $data Input data.
	 * @param string                    $prefix Field name prefix.
	 */
	public function add_array( $data, $prefix = '' ) {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( $prefix ) {
					$this->add_array( $value, $prefix . '[' . $key . ']' );
				} else {
					$this->add_array( $value, $key );
				}
			} elseif ( $prefix ) {
					$this->add_part(
						$prefix . '[' . ( is_numeric( $key ) ? '' : $key ) . ']',
						$value
					);
			} else {
				$this->add_part( $key, $value );
			}
		}
	}

	/**
	 * Add new payload part.
	 *
	 * @param string $key Part name.
	 * @param any    $value Part value.
	 */
	public function add_part( $key, $value ) {
		$this->add_part_header();
		$this->data .=
			'Content-Disposition: form-data; name="' . $key . '"' . self::EOL;
		$this->data .= self::EOL;
		$this->data .= $value . self::EOL;
	}

	/**
	 * Add file to the payload.
	 *
	 * @param string      $key Part name.
	 * @param string      $filename File name.
	 * @param string      $type File type.
	 * @param string|null $content File content.
	 */
	public function add_file( $key, $filename, $type, $content = null ) {
		$this->add_part_header();
		$this->data .= 'Content-Disposition: form-data; name="' . $key . '"; filename="' . basename( $filename ) . '"' . self::EOL;

		$this->data .= 'Content-Type: ' . $type . self::EOL;
		$this->data .= self::EOL;

		if ( ! $content ) {
			$content = file_get_contents( $filename );
		}

		$this->data .= $content . self::EOL;
	}

	/**
	 * Get bounded mime content type.
	 *
	 * @return string Mime content type.
	 */
	public function content_type() {
		return 'multipart/form-data; boundary=------' . $this->mime_boundary;
	}

	/**
	 * Get content data.
	 *
	 * @return string Content data.
	 */
	public function data() {
		return $this->data .= '--------' . $this->mime_boundary . '--' . self::EOL;
	}

	/**
	 * Decodes multipart/form-data payloads and returns array of field descriptiors.
	 *
	 * @return array Field descriptors.
	 */
	public function decode() {
		$fields = array();

		$lines        = preg_split( '/' . self::EOL_RE . '/', $this->data );
		$name         = null;
		$filename     = null;
		$content_type = null;
		$value        = '';
		$buffering    = false;
		foreach ( $lines as $line ) {
			if ( empty( trim( $line ) ) ) {
				if ( null !== $name ) {
					$buffering = true;
				}
				continue;
			}

			if ( preg_match( '/^--+' . $this->mime_boundary . '-*' . self::EOL_RE . '?/', $line ) ) {
				if ( $name ) {
					if ( $filename && null === $content_type ) {
						$content_type = 'application/octet-stream';
					}
					$fields[]     = array(
						'name'         => $name,
						'filename'     => $filename,
						'content-type' => $content_type,
						'value'        => $value,
					);
					$name         = null;
					$filename     = null;
					$content_type = null;
					$value        = '';
					$buffering    = false;
				}
				continue;
			}

			if ( $buffering ) {
				$value .= $line . self::EOL;
			}

			if ( null === $name && preg_match( '/name="((?:.(?!"))+.)"/', $line, $match ) ) {
				$name = $match[1];

				if ( preg_match( '/filename="((?:.(?!"))+.)"/', $line, $match ) ) {
					$filename = $match[1];
				}
			}

			if ( $filename ) {
				if ( preg_match( '/Content-Type\s*\:([^;]+)/i', $line, $match ) ) {
					$content_type = trim( $match[1] );
				}
			}
		}

		return $fields;
	}
}
