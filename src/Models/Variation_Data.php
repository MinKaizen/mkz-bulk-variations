<?php
/**
 * Variation Data Model
 *
 * @package BulkVariations\Models
 */

namespace BulkVariations\Models;

/**
 * Variation_Data represents a single variation to be created
 */
class Variation_Data {

	/**
	 * Variation SKU
	 *
	 * @var string|null
	 */
	public $sku;

	/**
	 * Variation regular price
	 *
	 * @var float
	 */
	public $price;

	/**
	 * Variation attributes (attribute_name => term_value)
	 *
	 * @var array
	 */
	public $attributes;

	/**
	 * Row number from original input (for error reporting)
	 *
	 * @var int
	 */
	public $row_number;

	/**
	 * Validation errors for this variation
	 *
	 * @var array
	 */
	public $errors;

	/**
	 * Constructor
	 *
	 * @param array $data Variation data array.
	 * @param int   $row_number Row number from input.
	 */
	public function __construct( array $data, $row_number ) {
		$this->row_number = $row_number;
		$this->errors     = array();
		$this->attributes = array();

		// Extract SKU if present.
		if ( isset( $data['sku'] ) ) {
			$this->sku = sanitize_text_field( $data['sku'] );
			unset( $data['sku'] );
		}

		// Extract price (required).
		if ( isset( $data['price'] ) ) {
			$this->price = $this->sanitize_price( $data['price'] );
			unset( $data['price'] );
		}

		// Remaining fields are attributes.
		foreach ( $data as $key => $value ) {
			$this->attributes[ $key ] = sanitize_text_field( $value );
		}
	}

	/**
	 * Sanitize price value
	 *
	 * @param mixed $price Price value.
	 * @return float
	 */
	private function sanitize_price( $price ) {
		// Remove non-numeric characters except decimal point.
		$price = preg_replace( '/[^0-9.]/', '', $price );
		return floatval( $price );
	}

	/**
	 * Check if variation has errors
	 *
	 * @return bool
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}

	/**
	 * Add an error
	 *
	 * @param string $error Error message.
	 */
	public function add_error( $error ) {
		$this->errors[] = $error;
	}

	/**
	 * Convert to array representation
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'sku'        => $this->sku,
			'price'      => $this->price,
			'attributes' => $this->attributes,
			'row_number' => $this->row_number,
			'errors'     => $this->errors,
		);
	}
}
