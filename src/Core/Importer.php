<?php
/**
 * Importer Class
 *
 * @package BulkVariations\Core
 */

namespace BulkVariations\Core;

use BulkVariations\Models\Variation_Data;
use WC_Product_Variation;

/**
 * Importer handles the creation of WooCommerce variations
 */
class Importer {

	/**
	 * Product ID
	 *
	 * @var int
	 */
	private $product_id;

	/**
	 * Validator instance
	 *
	 * @var Validator
	 */
	private $validator;

	/**
	 * Constructor
	 *
	 * @param int $product_id Product ID.
	 */
	public function __construct( $product_id ) {
		$this->product_id = $product_id;
		$this->validator  = new Validator();
	}

	/**
	 * Import variations
	 *
	 * @param array $variations Array of Variation_Data objects.
	 * @return array Result with 'success', 'created', 'errors'.
	 */
	public function import_variations( $variations ) {
		$result = array(
			'success' => false,
			'created' => array(),
			'errors'  => array(),
		);

		// Get parent product.
		$product = wc_get_product( $this->product_id );

		if ( ! $product || $product->get_type() !== 'variable' ) {
			$result['errors'][] = __( 'Product must be a variable product.', 'mkz-bulk-variations' );
			return $result;
		}

		// Get or create attributes for the product.
		$attribute_mapping = $this->setup_product_attributes( $variations, $product );

		if ( empty( $attribute_mapping ) ) {
			$result['errors'][] = __( 'Failed to setup product attributes.', 'mkz-bulk-variations' );
			return $result;
		}

		// Create variations.
		foreach ( $variations as $variation_data ) {
			if ( $variation_data->has_errors() ) {
				$result['errors'][] = sprintf(
					/* translators: %d: row number */
					__( 'Row %d has validation errors', 'mkz-bulk-variations' ),
					$variation_data->row_number
				);
				continue;
			}

			$variation_id = $this->create_variation( $variation_data, $product, $attribute_mapping );

			if ( $variation_id ) {
				$result['created'][] = $variation_id;
			} else {
				$result['errors'][] = sprintf(
					/* translators: %d: row number */
					__( 'Failed to create variation for row %d', 'mkz-bulk-variations' ),
					$variation_data->row_number
				);
			}
		}

		$result['success'] = ! empty( $result['created'] );

		return $result;
	}

	/**
	 * Setup product attributes
	 *
	 * @param array      $variations Array of Variation_Data objects.
	 * @param \WC_Product $product Product object.
	 * @return array Attribute mapping: attribute_name => taxonomy.
	 */
	private function setup_product_attributes( $variations, $product ) {
		$parser            = new Parser();
		$unique_attributes = $parser->get_unique_attribute_terms( $variations );
		$attribute_mapping = array();
		$product_attributes = array();

		foreach ( $unique_attributes as $attr_name => $terms ) {
			// Create or get attribute taxonomy.
			$attribute_id = $this->get_or_create_attribute( $attr_name );

			if ( ! $attribute_id ) {
				continue;
			}

			$taxonomy = wc_attribute_taxonomy_name_by_id( $attribute_id );

			// Register taxonomy if needed.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, 'product' );
			}

			// Create or get terms for this attribute.
			$term_ids = array();
			foreach ( $terms as $term_name ) {
				$term_id = $this->get_or_create_term( $term_name, $taxonomy );
				if ( $term_id ) {
					$term_ids[] = $term_id;
				}
			}

			// Set terms for the product.
			wp_set_object_terms( $this->product_id, $term_ids, $taxonomy );

			// Add to product attributes.
			$attribute = new \WC_Product_Attribute();
			$attribute->set_id( $attribute_id );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( $term_ids );
			$attribute->set_visible( true );
			$attribute->set_variation( true );

			$product_attributes[] = $attribute;
			$attribute_mapping[ $attr_name ] = $taxonomy;
		}

		// Save product attributes.
		$product->set_attributes( $product_attributes );
		$product->save();

		return $attribute_mapping;
	}

	/**
	 * Get or create attribute
	 *
	 * @param string $attribute_name Attribute name.
	 * @return int|false Attribute ID or false on failure.
	 */
	private function get_or_create_attribute( $attribute_name ) {
		// Check if attribute exists.
		$attribute_id = $this->validator->get_existing_attribute_id( $attribute_name );

		if ( $attribute_id ) {
			return $attribute_id;
		}

		// Create new attribute.
		$attribute_id = wc_create_attribute(
			array(
				'name'         => $attribute_name,
				'slug'         => sanitize_title( $attribute_name ),
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		return is_wp_error( $attribute_id ) ? false : $attribute_id;
	}

	/**
	 * Get or create term for attribute
	 *
	 * @param string $term_name Term name.
	 * @param string $taxonomy Taxonomy name.
	 * @return int|false Term ID or false on failure.
	 */
	private function get_or_create_term( $term_name, $taxonomy ) {
		// Check if term exists.
		$term_id = $this->validator->get_existing_term_id( $term_name, $taxonomy );

		if ( $term_id ) {
			return $term_id;
		}

		// Create new term.
		$term = wp_insert_term( $term_name, $taxonomy );

		if ( is_wp_error( $term ) ) {
			return false;
		}

		return $term['term_id'];
	}

	/**
	 * Create a single variation
	 *
	 * @param Variation_Data $variation_data Variation data.
	 * @param \WC_Product     $product Parent product.
	 * @param array          $attribute_mapping Attribute mapping.
	 * @return int|false Variation ID or false on failure.
	 */
	private function create_variation( $variation_data, $product, $attribute_mapping ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $this->product_id );
		$variation->set_regular_price( $variation_data->price );

		// Set SKU if provided.
		if ( ! empty( $variation_data->sku ) ) {
			$variation->set_sku( $variation_data->sku );
		}

		// Set variation attributes.
		$attributes = array();
		foreach ( $variation_data->attributes as $attr_name => $attr_value ) {
			if ( isset( $attribute_mapping[ $attr_name ] ) ) {
				$taxonomy = $attribute_mapping[ $attr_name ];
				
				// Get term slug.
				$term = get_term_by( 'name', $attr_value, $taxonomy );
				if ( $term ) {
					$attributes[ $taxonomy ] = $term->slug;
				}
			}
		}

		$variation->set_attributes( $attributes );

		// Set status to publish.
		$variation->set_status( 'publish' );

		// Save variation.
		$variation_id = $variation->save();

		return $variation_id ? $variation_id : false;
	}
}
