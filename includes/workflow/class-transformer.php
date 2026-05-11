<?php
/**
 * Workflow Transformer (DTO/ETL Mapping)
 *
 * @package pathwaybridgesuite
 */

namespace PATHWAY_BRIDGE_SUITE\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Handles data transformation between different formats.
 */
class Transformer {

	/**
	 * Apply mapping to a payload.
	 *
	 * @param array $payload The raw data.
	 * @param array $mapping The mapping rules.
	 * @return array The transformed data.
	 */
	public static function map( $payload, $mapping ) {
		$output = array();

		foreach ( $mapping as $target_field => $source_config ) {
			if ( is_string( $source_config ) ) {
				$value = self::get_value_by_path( $payload, $source_config );
				self::set_value_by_path( $output, $target_field, $value );
			} elseif ( is_array( $source_config ) ) {
				$value = self::get_value_by_path( $payload, $source_config['path'] ?? '' );
				
				if ( isset( $source_config['transform'] ) ) {
					$value = self::apply_transform( $value, $source_config['transform'] );
				}
				
				self::set_value_by_path( $output, $target_field, $value );
			}
		}

		return $output;
	}

	/**
	 * Get value from a multi-dimensional array using a dot-notation path.
	 */
	private static function get_value_by_path( $data, $path ) {
		if ( empty( $path ) ) {
			return $data;
		}

		$keys = explode( '.', $path );
		foreach ( $keys as $key ) {
			if ( is_array( $data ) && isset( $data[ $key ] ) ) {
				$data = $data[ $key ];
			} else {
				return null;
			}
		}

		return $data;
	}

	/**
	 * Set value in a multi-dimensional array using a dot-notation path.
	 */
	private static function set_value_by_path( &$data, $path, $value ) {
		$keys = explode( '.', $path );
		foreach ( $keys as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
				$data[ $key ] = array();
			}
			$data = &$data[ $key ];
		}
		$data = $value;
	}

	/**
	 * Apply a specific transformation to a value.
	 */
	private static function apply_transform( $value, $transform ) {
		if ( is_array( $transform ) ) {
			foreach ( $transform as $t ) {
				$value = self::apply_transform( $value, $t );
			}
			return $value;
		}

		switch ( $transform ) {
			case 'to_string':
				return (string) $value;
			case 'to_int':
				return (int) $value;
			case 'to_float':
			case 'decimal':
				return (float) $value;
			case 'to_bool':
				return (bool) $value;
			case 'json_encode':
				return json_encode( $value );
			case 'json_decode':
				return json_decode( $value, true );
			case 'csv':
				return is_array( $value ) ? implode( ',', $value ) : $value;
			case 'spaced':
				return is_array( $value ) ? implode( ' ', $value ) : $value;
			case 'expand_abbreviations':
				return self::expand_abbreviations( $value );
			case 'date_iso8601':
				return date( 'c', is_numeric( $value ) ? $value : strtotime( $value ) );
			default:
				return $value;
		}
	}

	/**
	 * SEO specific expansion.
	 */
	private static function expand_abbreviations( $text ) {
		if ( ! is_string( $text ) ) return $text;
		
		$dictionary = array(
			'AP' => 'Pathway Academy',
			'PAZ' => 'Pathway Academy Zone',
			'SEMH' => 'Social, Emotional and Mental Health',
		);

		return strtr( $text, $dictionary );
	}
}
