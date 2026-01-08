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
	 * Analyze variations to determine what will be created, updated, or unchanged
	 *
	 * @param array $variations Array of Variation_Data objects.
	 * @return array Analysis with 'variations' and 'attributes' breakdown.
	 */
	public function analyze_variations( $variations ) {
		$result = array(
			'variations' => array(
				'new'       => 0,
				'update'    => 0,
				'unchanged' => 0,
			),
			'attributes' => array(
				'new'       => 0,
				'update'    => 0,
				'unchanged' => 0,
			),
		);

		// Get parent product.
		$product = wc_get_product( $this->product_id );

		if ( ! $product ) {
			return $result;
		}

		// Analyze attributes.
		$parser = new Parser();
		$unique_attributes = $parser->get_unique_attribute_terms( $variations );

		foreach ( $unique_attributes as $attr_name => $terms ) {
			$attribute_slug = sanitize_title( $attr_name );
			$attribute_id = $this->validator->get_existing_attribute_id( $attr_name );

			if ( $attribute_id ) {
				// Attribute exists - check if we're adding new terms.
				$taxonomy = wc_attribute_taxonomy_name( $attribute_slug );
				if ( empty( $taxonomy ) ) {
					$taxonomy = 'pa_' . $attribute_slug;
				}

				$has_new_terms = false;
				foreach ( $terms as $term_name ) {
					$term_id = $this->validator->get_existing_term_id( $term_name, $taxonomy );
					if ( ! $term_id ) {
						$has_new_terms = true;
						break;
					}
				}

				if ( $has_new_terms ) {
					$result['attributes']['update']++;
				} else {
					$result['attributes']['unchanged']++;
				}
			} else {
				// New attribute.
				$result['attributes']['new']++;
			}
		}

		// Build attribute mapping for variation analysis.
		$attribute_mapping = array();
		foreach ( $unique_attributes as $attr_name => $terms ) {
			$attribute_slug = sanitize_title( $attr_name );
			$taxonomy = wc_attribute_taxonomy_name( $attribute_slug );
			if ( empty( $taxonomy ) ) {
				$taxonomy = 'pa_' . $attribute_slug;
			}
			$attribute_mapping[ $attr_name ] = $taxonomy;
		}

		// Analyze variations.
		foreach ( $variations as $variation_data ) {
			if ( $variation_data->has_errors() ) {
				continue;
			}

			// Build attributes array.
			$attributes = array();
			foreach ( $variation_data->attributes as $attr_name => $attr_value ) {
				if ( isset( $attribute_mapping[ $attr_name ] ) ) {
					$taxonomy = $attribute_mapping[ $attr_name ];
					$term = get_term_by( 'name', $attr_value, $taxonomy );
					if ( $term ) {
						$attributes[ $taxonomy ] = $term->slug;
					}
				}
			}

			// Check if variation exists.
			$existing_variation_id = $this->find_variation_by_attributes( $this->product_id, $attributes );

			if ( $existing_variation_id ) {
				$existing_variation = wc_get_product( $existing_variation_id );
				if ( $existing_variation ) {
					$current_price = $existing_variation->get_regular_price();
					if ( (string) $current_price === (string) $variation_data->price ) {
						$result['variations']['unchanged']++;
					} else {
						$result['variations']['update']++;
					}
				}
			} else {
				$result['variations']['new']++;
			}
		}

		return $result;
	}

	/**
	 * Convert product to variable type if needed
	 *
	 * @param \WC_Product $product Product object.
	 * @return bool|WP_Error True if converted, false if already variable, WP_Error on failure.
	 */
	private function convert_to_variable_product( $product ) {
		$product_id   = $this->product_id;
		$current_type = $product->get_type();

		error_log( "[Bulk Variations Importer] Current product type: {$current_type}" );

		// 1. Already variable - no conversion needed.
		if ( $current_type === 'variable' ) {
			error_log( '[Bulk Variations Importer] Product is already variable, skipping conversion' );
			return false;
		}

		// 2. Check if product type can be converted.
		$convertible_types = array( 'simple', 'grouped', 'external' );
		if ( ! in_array( $current_type, $convertible_types, true ) ) {
			error_log( "[Bulk Variations Importer] Cannot convert {$current_type} to variable product" );
			return new \WP_Error(
				'invalid_product_type',
				sprintf(
					/* translators: %s: product type */
					__( 'Cannot convert %s product to variable product.', 'mkz-bulk-variations' ),
					$current_type
				)
			);
		}

		error_log( "[Bulk Variations Importer] Converting product ID {$product_id} from {$current_type} to variable" );

		// 3. Update the Product Type Term in the database
		// This is the core database change.
		$term_update = wp_set_object_terms( $product_id, 'variable', 'product_type' );

		if ( is_wp_error( $term_update ) ) {
			return $term_update;
		}

		// 4. Clear ALL caches to ensure WooCommerce fetches fresh data
		// This forces the Data Store to see the new 'variable' term
		wp_cache_delete( $product_id, 'products' );
		clean_post_cache( $product_id );
		wc_delete_product_transients( $product_id );

		// 5. Re-instantiate the product object
		// By calling wc_get_product again, WC_Product_Factory detects the new 'variable' term
		$variable_product = wc_get_product( $product_id );

		// 6. Validation Check
		if ( ! $variable_product || ! $variable_product->is_type( 'variable' ) ) {
			// Final fallback: Manually force the variable class if the factory failed
			$variable_product = new \WC_Product_Variable( $product_id );
		}

		if ( ! $variable_product || ! $variable_product->is_type( 'variable' ) ) {
			return new \WP_Error(
				'conversion_failed',
				__( 'Failed to convert product to variable type in the Data Store.', 'mkz-bulk-variations' )
			);
		}

		// 7. Syncing preserved data (Optional but recommended)
		// Since it's the same ID, standard fields like name/description usually persist
		// automatically in the DB, but we save to be sure the object is synced.
		$variable_product->set_sku( $product->get_sku() );
		$variable_product->set_manage_stock( $product->get_manage_stock() );
		$variable_product->save();

		error_log( "[Bulk Variations Importer] Product ID {$product_id} successfully converted to variable." );

		return true;
	}

	/**
	 * Import variations
	 *
	 * @param array $variations Array of Variation_Data objects.
	 * @return array Result with 'success', 'created', 'updated', 'unchanged', 'errors'.
	 */
	public function import_variations( $variations ) {
		error_log( "[Bulk Variations Importer] Starting import for product {$this->product_id}" );
		error_log( "[Bulk Variations Importer] Variations to import: " . count( $variations ) );

		$result = array(
			'success'   => false,
			'created'   => array(),
			'updated'   => array(),
			'unchanged' => array(),
			'errors'    => array(),
			'converted' => false,
		);

		// Get parent product.
		$product = wc_get_product( $this->product_id );

		if ( ! $product ) {
			error_log( "[Bulk Variations Importer] Product {$this->product_id} not found" );
			$result['errors'][] = __( 'Product not found.', 'mkz-bulk-variations' );
			return $result;
		}

		error_log( "[Bulk Variations Importer] Product loaded: {$product->get_name()}, type: {$product->get_type()}" );

		// Convert product to variable if needed.
		try {
			$conversion_result = $this->convert_to_variable_product( $product );
			if ( is_wp_error( $conversion_result ) ) {
				error_log( '[Bulk Variations Importer] Conversion failed: ' . $conversion_result->get_error_message() );
				$result['errors'][] = $conversion_result->get_error_message();
				return $result;
			}

			if ( $conversion_result === true ) {
				error_log( '[Bulk Variations Importer] Product converted to variable type' );
				$result['converted'] = true;
				// Reload product after conversion.
				$product = wc_get_product( $this->product_id );
			}
		} catch ( \Exception $e ) {
			error_log( '[Bulk Variations Importer] Exception during conversion: ' . $e->getMessage() );
			$result['errors'][] = $e->getMessage();
			return $result;
		}

		// Get or create attributes for the product.
		try {
			error_log( '[Bulk Variations Importer] Setting up product attributes' );
			$attribute_mapping = $this->setup_product_attributes( $variations, $product );

			if ( empty( $attribute_mapping ) ) {
				error_log( '[Bulk Variations Importer] Attribute setup failed - empty mapping' );
				$result['errors'][] = __( 'Failed to setup product attributes.', 'mkz-bulk-variations' );
				return $result;
			}

			error_log( '[Bulk Variations Importer] Attributes set up successfully: ' . print_r( array_keys( $attribute_mapping ), true ) );
		} catch ( \Exception $e ) {
			error_log( '[Bulk Variations Importer] Exception during attribute setup: ' . $e->getMessage() );
			$result['errors'][] = $e->getMessage();
			return $result;
		}

		// Create or update variations.
		$variation_count = 0;
		foreach ( $variations as $variation_data ) {
			$variation_count++;

			if ( $variation_data->has_errors() ) {
				$error_msg = sprintf(
					/* translators: %d: row number */
					__( 'Row %d has validation errors', 'mkz-bulk-variations' ),
					$variation_data->row_number
				);
				error_log( "[Bulk Variations Importer] {$error_msg}" );
				$result['errors'][] = $error_msg;
				continue;
			}

			try {
				error_log( "[Bulk Variations Importer] Processing variation {$variation_count}/{" . count( $variations ) . "} (row {$variation_data->row_number})" );
				$operation_result = $this->create_or_update_variation( $variation_data, $product, $attribute_mapping );

				if ( $operation_result['id'] ) {
					if ( $operation_result['action'] === 'created' ) {
						error_log( "[Bulk Variations Importer] Variation created: ID {$operation_result['id']}" );
						$result['created'][] = $operation_result['id'];
					} elseif ( $operation_result['action'] === 'updated' ) {
						error_log( "[Bulk Variations Importer] Variation updated: ID {$operation_result['id']}" );
						$result['updated'][] = $operation_result['id'];
					} elseif ( $operation_result['action'] === 'unchanged' ) {
						error_log( "[Bulk Variations Importer] Variation unchanged: ID {$operation_result['id']}" );
						$result['unchanged'][] = $operation_result['id'];
					}
				} else {
					$error_msg = sprintf(
						/* translators: %d: row number */
						__( 'Failed to create/update variation for row %d', 'mkz-bulk-variations' ),
						$variation_data->row_number
					);
					error_log( "[Bulk Variations Importer] {$error_msg}" );
					$result['errors'][] = $error_msg;
				}
			} catch ( \Exception $e ) {
				error_log( "[Bulk Variations Importer] Exception processing variation for row {$variation_data->row_number}: " . $e->getMessage() );
				$result['errors'][] = sprintf(
					/* translators: 1: row number, 2: error message */
					__( 'Row %1$d error: %2$s', 'mkz-bulk-variations' ),
					$variation_data->row_number,
					$e->getMessage()
				);
			}
		}

		// Success if we created, updated, or found unchanged variations (and no errors)
		$result['success'] = ( ! empty( $result['created'] ) || ! empty( $result['updated'] ) || ! empty( $result['unchanged'] ) ) && empty( $result['errors'] );

		error_log( "[Bulk Variations Importer] Import complete. Created: " . count( $result['created'] ) . ", Updated: " . count( $result['updated'] ) . ", Unchanged: " . count( $result['unchanged'] ) . ", Errors: " . count( $result['errors'] ) );

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
		error_log( '[Bulk Variations Importer] Setting up product attributes' );

		$parser            = new Parser();
		$unique_attributes = $parser->get_unique_attribute_terms( $variations );

		error_log( '[Bulk Variations Importer] Unique attributes found: ' . print_r( $unique_attributes, true ) );

		$attribute_mapping = array();
		$product_attributes = array();

		foreach ( $unique_attributes as $attr_name => $terms ) {
			error_log( "[Bulk Variations Importer] Processing attribute: {$attr_name} with " . count( $terms ) . ' terms' );

			// Generate slug for the attribute.
			$attribute_slug = sanitize_title( $attr_name );
			error_log( "[Bulk Variations Importer] Attribute slug: {$attribute_slug}" );

			// Create or get attribute taxonomy.
			$attribute_id = $this->get_or_create_attribute( $attr_name, $attribute_slug );

			if ( ! $attribute_id ) {
				error_log( "[Bulk Variations Importer] ERROR: Failed to get/create attribute: {$attr_name}" );
				continue;
			}

			error_log( "[Bulk Variations Importer] Attribute ID for {$attr_name}: {$attribute_id}" );

			// Build taxonomy name manually in WooCommerce format: pa_{slug}.
			$taxonomy = wc_attribute_taxonomy_name( $attribute_slug );
			error_log( "[Bulk Variations Importer] Taxonomy name (from wc_attribute_taxonomy_name): {$taxonomy}" );

			// Fallback if empty - construct manually.
			if ( empty( $taxonomy ) ) {
				$taxonomy = 'pa_' . $attribute_slug;
				error_log( "[Bulk Variations Importer] Taxonomy name was empty, using fallback: {$taxonomy}" );
			}

			// Register taxonomy if needed.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				error_log( "[Bulk Variations Importer] Registering taxonomy: {$taxonomy}" );
				register_taxonomy(
					$taxonomy,
					'product',
					array(
						'labels'       => array(
							'name' => $attr_name,
						),
						'hierarchical' => false,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
						'public'       => false,
					)
				);
			}

		// Create or get terms for this attribute.
		$new_term_ids = array();
		foreach ( $terms as $term_name ) {
			$term_id = $this->get_or_create_term( $term_name, $taxonomy );
			if ( $term_id ) {
				error_log( "[Bulk Variations Importer] Term '{$term_name}' created/found with ID: {$term_id}" );
				$new_term_ids[] = $term_id;
			} else {
				error_log( "[Bulk Variations Importer] ERROR: Failed to create/get term: {$term_name} for {$taxonomy}" );
			}
		}

		// Get existing terms for this product and taxonomy to merge with new terms.
		$existing_term_ids = array();
		$existing_terms = wp_get_object_terms( $this->product_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $existing_terms ) && is_array( $existing_terms ) ) {
			$existing_term_ids = $existing_terms;
			error_log( "[Bulk Variations Importer] Found " . count( $existing_term_ids ) . " existing terms for {$taxonomy}" );
		}

		// Merge existing and new term IDs (remove duplicates).
		$all_term_ids = array_unique( array_merge( $existing_term_ids, $new_term_ids ) );
		error_log( "[Bulk Variations Importer] Total terms after merge: " . count( $all_term_ids ) . " (existing: " . count( $existing_term_ids ) . ", new: " . count( $new_term_ids ) . ")" );

		// Set terms for the product (this will now include both old and new terms).
		error_log( "[Bulk Variations Importer] Setting " . count( $all_term_ids ) . " terms for taxonomy {$taxonomy}" );
		wp_set_object_terms( $this->product_id, $all_term_ids, $taxonomy );

		// Add to product attributes.
		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( $attribute_id );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $all_term_ids );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$product_attributes[] = $attribute;
		$attribute_mapping[ $attr_name ] = $taxonomy;

			error_log( "[Bulk Variations Importer] Attribute {$attr_name} mapped to {$taxonomy}" );
		}

		// Save product attributes.
		error_log( '[Bulk Variations Importer] Saving product with ' . count( $product_attributes ) . ' attributes' );
		$product->set_attributes( $product_attributes );
		$product->save();
		error_log( '[Bulk Variations Importer] Product attributes saved' );

		return $attribute_mapping;
	}

	/**
	 * Get or create attribute
	 *
	 * @param string $attribute_name Attribute name.
	 * @param string $attribute_slug Attribute slug.
	 * @return int|false Attribute ID or false on failure.
	 */
	private function get_or_create_attribute( $attribute_name, $attribute_slug ) {
		// Check if attribute exists.
		$attribute_id = $this->validator->get_existing_attribute_id( $attribute_name );

		if ( $attribute_id ) {
			error_log( "[Bulk Variations Importer] Attribute '{$attribute_name}' already exists with ID: {$attribute_id}" );
			return $attribute_id;
		}

		// Create new attribute.
		error_log( "[Bulk Variations Importer] Creating new attribute '{$attribute_name}' with slug '{$attribute_slug}'" );
		$attribute_id = wc_create_attribute(
			array(
				'name'         => $attribute_name,
				'slug'         => $attribute_slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		if ( is_wp_error( $attribute_id ) ) {
			error_log( '[Bulk Variations Importer] ERROR creating attribute: ' . $attribute_id->get_error_message() );
			return false;
		}

		error_log( "[Bulk Variations Importer] Attribute created successfully with ID: {$attribute_id}" );

		// Clear attribute cache to ensure it's available immediately.
		delete_transient( 'wc_attribute_taxonomies' );
		\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );

		return $attribute_id;
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
		error_log( "[Bulk Variations Importer] Creating variation - Price: {$variation_data->price}, SKU: {$variation_data->sku}" );

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $this->product_id );
		$variation->set_regular_price( $variation_data->price );

		// Set SKU if provided.
		if ( ! empty( $variation_data->sku ) ) {
			error_log( "[Bulk Variations Importer] Setting SKU: {$variation_data->sku}" );
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
					error_log( "[Bulk Variations Importer] Mapping attribute {$attr_name} ({$taxonomy}): {$attr_value} -> {$term->slug}" );
					$attributes[ $taxonomy ] = $term->slug;
				} else {
					error_log( "[Bulk Variations Importer] WARNING: Term not found for {$attr_name} ({$taxonomy}): {$attr_value}" );
				}
			} else {
				error_log( "[Bulk Variations Importer] WARNING: Attribute not in mapping: {$attr_name}" );
			}
		}

		error_log( '[Bulk Variations Importer] Setting attributes: ' . print_r( $attributes, true ) );
		$variation->set_attributes( $attributes );

		// Set status to publish.
		$variation->set_status( 'publish' );

		// Save variation.
		error_log( '[Bulk Variations Importer] Saving variation...' );
		$variation_id = $variation->save();

		if ( $variation_id ) {
			error_log( "[Bulk Variations Importer] Variation saved successfully: ID {$variation_id}" );
		} else {
			error_log( '[Bulk Variations Importer] ERROR: Variation save returned false/0' );
		}

		return $variation_id ? $variation_id : false;
	}

	/**
	 * Create or update a variation (update if matching attributes exist)
	 *
	 * @param Variation_Data $variation_data Variation data.
	 * @param \WC_Product     $product Parent product.
	 * @param array          $attribute_mapping Attribute mapping.
	 * @return array Array with 'id' and 'action' (created|updated|unchanged).
	 */
	private function create_or_update_variation( $variation_data, $product, $attribute_mapping ) {
		// Build the attributes array for comparison.
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

		// Check if variation with these attributes already exists.
		$existing_variation_id = $this->find_variation_by_attributes( $this->product_id, $attributes );

		if ( $existing_variation_id ) {
			// Variation exists - check if we need to update it.
			error_log( "[Bulk Variations Importer] Found existing variation ID: {$existing_variation_id}" );
			$existing_variation = wc_get_product( $existing_variation_id );

			if ( ! $existing_variation ) {
				error_log( "[Bulk Variations Importer] ERROR: Could not load existing variation {$existing_variation_id}" );
				return array( 'id' => false, 'action' => 'error' );
			}

			$current_price = $existing_variation->get_regular_price();
			$new_price = $variation_data->price;

			// Compare prices (case insensitive for price comparison).
			if ( (string) $current_price === (string) $new_price ) {
				error_log( "[Bulk Variations Importer] Variation {$existing_variation_id} price unchanged: {$current_price}" );
				return array( 'id' => $existing_variation_id, 'action' => 'unchanged' );
			}

			// Update the price.
			error_log( "[Bulk Variations Importer] Updating variation {$existing_variation_id} price from {$current_price} to {$new_price}" );
			$existing_variation->set_regular_price( $new_price );

			// Update SKU if provided.
			if ( ! empty( $variation_data->sku ) ) {
				$existing_variation->set_sku( $variation_data->sku );
			}

			$existing_variation->save();

			return array( 'id' => $existing_variation_id, 'action' => 'updated' );
		}

		// No existing variation - create new one.
		error_log( "[Bulk Variations Importer] No existing variation found, creating new one" );
		$variation_id = $this->create_variation( $variation_data, $product, $attribute_mapping );

		return array(
			'id' => $variation_id,
			'action' => $variation_id ? 'created' : 'error'
		);
	}

	/**
	 * Find a variation by its attributes (case insensitive)
	 *
	 * @param int   $product_id Product ID.
	 * @param array $attributes Attributes array (taxonomy => slug).
	 * @return int|false Variation ID if found, false otherwise.
	 */
	private function find_variation_by_attributes( $product_id, $attributes ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || $product->get_type() !== 'variable' ) {
			return false;
		}

		$existing_variations = $product->get_available_variations();

		foreach ( $existing_variations as $existing_variation ) {
			$variation_id = $existing_variation['variation_id'];
			$variation_obj = wc_get_product( $variation_id );

			if ( ! $variation_obj ) {
				continue;
			}

			$variation_attributes = $variation_obj->get_attributes();

			// Normalize both arrays for case-insensitive comparison.
			$normalized_variation_attrs = array();
			foreach ( $variation_attributes as $key => $value ) {
				$normalized_variation_attrs[ strtolower( $key ) ] = strtolower( $value );
			}

			$normalized_search_attrs = array();
			foreach ( $attributes as $key => $value ) {
				$normalized_search_attrs[ strtolower( $key ) ] = strtolower( $value );
			}

			// Check if all attributes match.
			if ( count( $normalized_variation_attrs ) === count( $normalized_search_attrs ) ) {
				$match = true;
				foreach ( $normalized_search_attrs as $key => $value ) {
					if ( ! isset( $normalized_variation_attrs[ $key ] ) || $normalized_variation_attrs[ $key ] !== $value ) {
						$match = false;
						break;
					}
				}

				if ( $match ) {
					return $variation_id;
				}
			}
		}

		return false;
	}
}
