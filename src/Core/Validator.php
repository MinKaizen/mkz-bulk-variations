<?php
/**
 * Validator Class
 *
 * @package BulkVariations\Core
 */

namespace BulkVariations\Core;

use BulkVariations\Models\Variation_Data;

/**
 * Validator checks variation data integrity before import
 */
class Validator {

	/**
	 * Validate variations array
	 *
	 * @param array $variations Array of Variation_Data objects.
	 * @param int   $product_id Product ID to validate against.
	 * @return array Array with 'valid' (bool) and 'variations' (updated array).
	 */
	public function validate_variations( $variations, $product_id ) {
		$all_valid = true;

		foreach ( $variations as $variation ) {
			// Validate price.
			if ( ! $this->validate_price( $variation ) ) {
				$all_valid = false;
			}

			// Validate SKU uniqueness if present.
			if ( ! empty( $variation->sku ) && ! $this->validate_sku( $variation, $product_id ) ) {
				$all_valid = false;
			}

			// Validate attributes.
			if ( ! $this->validate_attributes( $variation ) ) {
				$all_valid = false;
			}
		}

		return array(
			'valid'      => $all_valid,
			'variations' => $variations,
		);
	}

	/**
	 * Validate price field
	 *
	 * @param Variation_Data $variation Variation object.
	 * @return bool
	 */
	private function validate_price( $variation ) {
		if ( empty( $variation->price ) || $variation->price <= 0 ) {
			$variation->add_error(
				sprintf(
					/* translators: %d: row number */
					__( 'Row %d: Invalid or missing price', 'mkz-bulk-variations' ),
					$variation->row_number
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Validate SKU uniqueness
	 *
	 * @param Variation_Data $variation Variation object.
	 * @param int            $product_id Product ID.
	 * @return bool
	 */
	private function validate_sku( $variation, $product_id ) {
		// Check if SKU already exists in WooCommerce.
		$existing_product_id = wc_get_product_id_by_sku( $variation->sku );

		if ( $existing_product_id && $existing_product_id !== $product_id ) {
			$variation->add_error(
				sprintf(
					/* translators: 1: row number, 2: SKU */
					__( 'Row %1$d: SKU "%2$s" already exists', 'mkz-bulk-variations' ),
					$variation->row_number,
					$variation->sku
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Validate attributes
	 *
	 * @param Variation_Data $variation Variation object.
	 * @return bool
	 */
	private function validate_attributes( $variation ) {
		if ( empty( $variation->attributes ) ) {
			$variation->add_error(
				sprintf(
					/* translators: %d: row number */
					__( 'Row %d: No attributes found', 'mkz-bulk-variations' ),
					$variation->row_number
				)
			);
			return false;
		}

		// Check for empty attribute values.
		foreach ( $variation->attributes as $attr_name => $attr_value ) {
			if ( empty( trim( $attr_value ) ) ) {
				$variation->add_error(
					sprintf(
						/* translators: 1: row number, 2: attribute name */
						__( 'Row %1$d: Attribute "%2$s" has an empty value', 'mkz-bulk-variations' ),
						$variation->row_number,
						$attr_name
					)
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if attribute exists in WooCommerce (case-insensitive)
	 *
	 * @param string $attribute_name Attribute name.
	 * @return int|false Attribute ID if exists, false otherwise.
	 */
	public function get_existing_attribute_id( $attribute_name ) {
		$taxonomies = wc_get_attribute_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			if ( strcasecmp( $taxonomy->attribute_label, $attribute_name ) === 0 ) {
				return $taxonomy->attribute_id;
			}
		}

		return false;
	}

	/**
	 * Check if term exists for an attribute (case-insensitive)
	 *
	 * @param string $term_name Term name.
	 * @param string $taxonomy Taxonomy name.
	 * @return int|false Term ID if exists, false otherwise.
	 */
	public function get_existing_term_id( $term_name, $taxonomy ) {
		$term = get_term_by( 'name', $term_name, $taxonomy, ARRAY_A );

		if ( ! $term ) {
			// Try case-insensitive search.
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			foreach ( $terms as $existing_term ) {
				if ( strcasecmp( $existing_term->name, $term_name ) === 0 ) {
					return $existing_term->term_id;
				}
			}

			return false;
		}

		return $term['term_id'];
	}
}
