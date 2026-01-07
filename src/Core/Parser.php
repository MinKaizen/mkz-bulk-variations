<?php
/**
 * Parser Class
 *
 * @package BulkVariations\Core
 */

namespace BulkVariations\Core;

use BulkVariations\Models\Variation_Data;

/**
 * Parser handles CSV/TSV input parsing with header normalization
 */
class Parser {

	/**
	 * Parse input data (CSV or TSV)
	 *
	 * @param string $input Raw input string.
	 * @return array Array with 'success', 'data', 'errors', 'headers'.
	 */
	public function parse_input( $input ) {
		$result = array(
			'success' => false,
			'data'    => array(),
			'errors'  => array(),
			'headers' => array(),
		);

		// Trim whitespace.
		$input = trim( $input );

		if ( empty( $input ) ) {
			$result['errors'][] = __( 'Input data is empty.', 'mkz-bulk-variations' );
			return $result;
		}

		// Detect delimiter (tab or comma).
		$delimiter = $this->detect_delimiter( $input );

		// Parse CSV/TSV.
		$rows = $this->parse_csv( $input, $delimiter );

		if ( empty( $rows ) ) {
			$result['errors'][] = __( 'No valid rows found.', 'mkz-bulk-variations' );
			return $result;
		}

		// First row is always treated as headers.
		$raw_headers = array_shift( $rows );
		$headers     = $this->normalize_headers( $raw_headers );

		$result['headers'] = $headers;

		// Validate required Price column.
		if ( ! $this->has_price_column( $headers ) ) {
			$result['errors'][] = __( 'Missing required column: Price', 'mkz-bulk-variations' );
			return $result;
		}

		// Parse data rows.
		$variations = array();
		$row_number = 2; // Start from row 2 (after header).

		foreach ( $rows as $row ) {
			if ( empty( array_filter( $row ) ) ) {
				// Skip empty rows.
				continue;
			}

			// Combine headers with row data.
			$row_data = $this->combine_headers_with_row( $headers, $row, $row_number );

			if ( ! empty( $row_data ) ) {
				$variations[] = new Variation_Data( $row_data, $row_number );
			}

			$row_number++;
		}

		$result['success'] = true;
		$result['data']    = $variations;

		return $result;
	}

	/**
	 * Detect delimiter (tab or comma)
	 *
	 * @param string $input Input string.
	 * @return string Delimiter character.
	 */
	private function detect_delimiter( $input ) {
		$first_line = strtok( $input, "\n" );

		$comma_count = substr_count( $first_line, ',' );
		$tab_count   = substr_count( $first_line, "\t" );

		return $tab_count > $comma_count ? "\t" : ',';
	}

	/**
	 * Parse CSV/TSV string into array of rows
	 *
	 * @param string $input Input string.
	 * @param string $delimiter Delimiter character.
	 * @return array Array of rows.
	 */
	private function parse_csv( $input, $delimiter ) {
		$rows = array();
		$lines = explode( "\n", $input );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$row = str_getcsv( $line, $delimiter );
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Normalize headers to Proper Case
	 *
	 * @param array $headers Raw header array.
	 * @return array Normalized headers.
	 */
	private function normalize_headers( $headers ) {
		return array_map( array( $this, 'to_proper_case' ), $headers );
	}

	/**
	 * Convert string to Proper Case (Title Case)
	 *
	 * @param string $string Input string.
	 * @return string Proper case string.
	 */
	private function to_proper_case( $string ) {
		$string = trim( $string );
		$string = mb_convert_case( $string, MB_CASE_TITLE, 'UTF-8' );
		return $string;
	}

	/**
	 * Check if headers contain a Price column (case-insensitive)
	 *
	 * @param array $headers Header array.
	 * @return bool
	 */
	private function has_price_column( $headers ) {
		foreach ( $headers as $header ) {
			if ( strtolower( $header ) === 'price' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Combine headers with row data
	 *
	 * @param array $headers Header array.
	 * @param array $row Row data array.
	 * @param int   $row_number Row number for error reporting.
	 * @return array Associative array with headers as keys.
	 */
	private function combine_headers_with_row( $headers, $row, $row_number ) {
		$result = array();
		$header_count = count( $headers );
		$row_count    = count( $row );

		// Handle mismatched column counts.
		if ( $row_count < $header_count ) {
			// Pad row with empty strings.
			$row = array_pad( $row, $header_count, '' );
		} elseif ( $row_count > $header_count ) {
			// Truncate row to match header count.
			$row = array_slice( $row, 0, $header_count );
		}

		// Combine headers and row values.
		foreach ( $headers as $index => $header ) {
			$value = isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
			
			// Convert header to lowercase for internal processing, but store with proper case.
			$key = strtolower( $header );
			
			if ( $key === 'price' ) {
				$result['price'] = $value;
			} elseif ( $key === 'sku' ) {
				$result['sku'] = $value;
			} else {
				// This is an attribute - use proper case as key.
				$result[ $header ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Get attribute names from headers (exclude Price and SKU)
	 *
	 * @param array $headers Header array.
	 * @return array Attribute names.
	 */
	public function get_attribute_names( $headers ) {
		$attributes = array();

		foreach ( $headers as $header ) {
			$lower = strtolower( $header );
			if ( $lower !== 'price' && $lower !== 'sku' ) {
				$attributes[] = $header;
			}
		}

		return $attributes;
	}

	/**
	 * Get unique attribute terms from variations
	 *
	 * @param array $variations Array of Variation_Data objects.
	 * @return array Associative array: attribute_name => [unique_terms].
	 */
	public function get_unique_attribute_terms( $variations ) {
		$unique_terms = array();

		foreach ( $variations as $variation ) {
			foreach ( $variation->attributes as $attr_name => $term_value ) {
				if ( ! isset( $unique_terms[ $attr_name ] ) ) {
					$unique_terms[ $attr_name ] = array();
				}

				if ( ! in_array( $term_value, $unique_terms[ $attr_name ], true ) ) {
					$unique_terms[ $attr_name ][] = $term_value;
				}
			}
		}

		return $unique_terms;
	}
}
