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
	 * @param int    $product_id Product ID to match existing variations.
	 * @return array Array with 'success', 'data', 'errors', 'headers'.
	 */
	public function parse_input( $input, $product_id = 0 ) {
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

		// Match with existing variations if product_id is provided.
		if ( $product_id > 0 ) {
			$variations = $this->match_existing_variations( $variations, $product_id, $headers );
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

	/**
	 * Match new variations with existing variations
	 *
	 * @param array $new_variations Array of Variation_Data objects from CSV.
	 * @param int   $product_id Product ID.
	 * @param array $headers Headers from CSV input.
	 * @return array Combined array with both new and existing variations.
	 */
	private function match_existing_variations( $new_variations, $product_id, $headers ) {
		// Get existing variations from the product.
		$product = wc_get_product( $product_id );

		if ( ! $product || ( $product->get_type() !== 'variable' && ! in_array( $product->get_type(), array( 'simple', 'grouped', 'external' ), true ) ) ) {
			return $new_variations;
		}

		// If product is not variable yet, no existing variations to match.
		if ( $product->get_type() !== 'variable' ) {
			return $new_variations;
		}

		$existing_variations = $product->get_children();

		if ( empty( $existing_variations ) ) {
			return $new_variations;
		}

		// Get attribute names from headers (exclude Price and SKU).
		$attribute_names = $this->get_attribute_names( $headers );

		// Build a map of existing variations with normalized attribute values.
		$existing_map = array();
		foreach ( $existing_variations as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}

			$attributes         = $variation->get_attributes();
			$normalized_attrs   = $this->normalize_attributes_for_display( $attributes, $attribute_names );
			$attribute_key      = $this->build_simple_attribute_key( $normalized_attrs, $attribute_names );
			
			$existing_map[ $attribute_key ] = array(
				'id'         => $variation_id,
				'price'      => $variation->get_regular_price(),
				'sku'        => $variation->get_sku(),
				'attributes' => $normalized_attrs,
			);
		}

		// Match new variations against existing ones.
		$matched_ids = array();
		foreach ( $new_variations as $new_variation ) {
			$new_key = $this->build_simple_attribute_key( $new_variation->attributes, $attribute_names );

			if ( isset( $existing_map[ $new_key ] ) ) {
				// This variation exists - check if price changed.
				$existing       = $existing_map[ $new_key ];
				$old_price      = floatval( $existing['price'] );
				$new_price      = floatval( $new_variation->price );

				$new_variation->existing_id = $existing['id'];
				$new_variation->old_price   = $old_price;

				if ( abs( $old_price - $new_price ) < 0.01 ) {
					// Price unchanged.
					$new_variation->status = 'unchanged';
				} else {
					// Price changed - update.
					$new_variation->status = 'update';
				}

				$matched_ids[] = $existing['id'];
			} else {
				// This is a new variation.
				$new_variation->status = 'new';
			}
		}

		// Add unmatched existing variations to the list (these won't be changed).
		foreach ( $existing_map as $key => $existing ) {
			if ( ! in_array( $existing['id'], $matched_ids, true ) ) {
				// Create a Variation_Data object for display purposes.
				$existing_data = new Variation_Data(
					array_merge(
						array(
							'price' => $existing['price'],
							'sku'   => $existing['sku'],
						),
						$this->normalize_attributes_for_display( $existing['attributes'], $attribute_names )
					),
					0 // Row number 0 for existing variations.
				);

				$existing_data->status      = 'unchanged';
				$existing_data->existing_id = $existing['id'];
				$existing_data->old_price   = floatval( $existing['price'] );

				// Add to the beginning of the array.
				array_unshift( $new_variations, $existing_data );
			}
		}

		return $new_variations;
	}

	/**
	 * Build a simple attribute key for matching
	 *
	 * @param array $attributes Normalized attributes array (name => value).
	 * @param array $attribute_names Attribute names in order.
	 * @return string Attribute key for matching.
	 */
	private function build_simple_attribute_key( $attributes, $attribute_names ) {
		$key_parts = array();

		foreach ( $attribute_names as $attr_name ) {
			$value = isset( $attributes[ $attr_name ] ) ? $attributes[ $attr_name ] : '';
			// Normalize: lowercase, trim, remove extra spaces.
			$value = preg_replace( '/\s+/', ' ', trim( strtolower( $value ) ) );
			$key_parts[] = $value;
		}

		return implode( '|', $key_parts );
	}

	/**
	 * Normalize WooCommerce attributes for display
	 *
	 * @param array $attributes WooCommerce variation attributes.
	 * @param array $attribute_names Attribute names from CSV.
	 * @return array Normalized attributes.
	 */
	private function normalize_attributes_for_display( $attributes, $attribute_names ) {
		$normalized = array();

		foreach ( $attribute_names as $attr_name ) {
			$taxonomy = wc_attribute_taxonomy_name( sanitize_title( $attr_name ) );
			
			if ( isset( $attributes[ $taxonomy ] ) ) {
				// Get term name from slug.
				$term = get_term_by( 'slug', $attributes[ $taxonomy ], $taxonomy );
				$normalized[ $attr_name ] = $term ? $term->name : $attributes[ $taxonomy ];
			} elseif ( isset( $attributes[ $attr_name ] ) ) {
				$normalized[ $attr_name ] = $attributes[ $attr_name ];
			} else {
				$normalized[ $attr_name ] = '';
			}
		}

		return $normalized;
	}
}
