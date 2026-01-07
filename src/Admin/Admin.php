<?php
/**
 * Admin Class
 *
 * @package BulkVariations\Admin
 */

namespace BulkVariations\Admin;

use BulkVariations\Core\Parser;
use BulkVariations\Core\Validator;
use BulkVariations\Core\Importer;
use BulkVariations\Core\Database;

/**
 * Admin handles admin UI and AJAX functionality
 */
class Admin {

	/**
	 * Initialize admin hooks
	 */
	public function init() {
		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_mkz_bulk_variations_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_mkz_bulk_variations_parse_input', array( $this, 'ajax_parse_input' ) );
		add_action( 'wp_ajax_mkz_bulk_variations_import', array( $this, 'ajax_import_variations' ) );

		// Product edit screen button.
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_edit_button' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Bulk Variations', 'mkz-bulk-variations' ),
			__( 'Bulk Variations', 'mkz-bulk-variations' ),
			'manage_woocommerce',
			'bulk-variations',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only load on our admin page and product edit screens.
		if ( 'tools_page_bulk-variations' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'mkz-bulk-variations-admin',
			MKZ_BULK_VARIATIONS_URL . 'assets/css/admin.css',
			array(),
			MKZ_BULK_VARIATIONS_VERSION
		);

		// Enqueue Select2 for product search.
		wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
		wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );

		// Enqueue admin JS.
		wp_enqueue_script(
			'mkz-bulk-variations-admin',
			MKZ_BULK_VARIATIONS_URL . 'assets/js/admin.js',
			array( 'jquery', 'select2' ),
			MKZ_BULK_VARIATIONS_VERSION,
			true
		);

		// Localize script with AJAX URL and nonce.
		wp_localize_script(
			'mkz-bulk-variations-admin',
			'mkzBulkVariations',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mkz-bulk-variations' ),
				'strings' => array(
					'searching'       => __( 'Searching...', 'mkz-bulk-variations' ),
					'noResults'       => __( 'No products found', 'mkz-bulk-variations' ),
					'selectProduct'   => __( 'Select a product', 'mkz-bulk-variations' ),
					'processing'      => __( 'Processing...', 'mkz-bulk-variations' ),
					'importSuccess'   => __( 'Import completed successfully!', 'mkz-bulk-variations' ),
					'importError'     => __( 'Import failed. Please check the errors.', 'mkz-bulk-variations' ),
					'parseError'      => __( 'Failed to parse input data.', 'mkz-bulk-variations' ),
					'emptyInput'      => __( 'Please paste or upload data.', 'mkz-bulk-variations' ),
					'selectProductFirst' => __( 'Please select a product first.', 'mkz-bulk-variations' ),
				),
			)
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
		include MKZ_BULK_VARIATIONS_PATH . 'views/admin-page.php';
	}

	/**
	 * Add button to product edit screen
	 */
	public function add_product_edit_button() {
		global $post;

		if ( ! $post ) {
			return;
		}

		$product = wc_get_product( $post->ID );

		if ( ! $product || $product->get_type() !== 'variable' ) {
			return;
		}

		$url = admin_url( 'tools.php?page=bulk-variations&product_id=' . $post->ID );
		?>
		<div class="options_group">
			<p class="form-field">
				<a href="<?php echo esc_url( $url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Bulk Add Variations', 'mkz-bulk-variations' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * AJAX: Search products
	 */
	public function ajax_search_products() {
		check_ajax_referer( 'mkz-bulk-variations', 'nonce' );

		$search = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 20,
			's'              => $search,
			'post_status'    => 'publish',
		);

		$query   = new \WP_Query( $args );
		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );
				$type    = $product ? $product->get_type() : 'unknown';
				
				// Show product type indicator.
				$type_label = '';
				if ( $type === 'variable' ) {
					$type_label = ' ✓';
				} elseif ( in_array( $type, array( 'simple', 'grouped', 'external' ), true ) ) {
					$type_label = ' (will convert to variable)';
				}
				
				// Decode HTML entities in product title.
				$title = html_entity_decode( get_the_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				
				$results[] = array(
					'id'   => get_the_ID(),
					'text' => $title . ' (#' . get_the_ID() . ')' . $type_label,
				);
			}
			wp_reset_postdata();
		}

		wp_send_json( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Parse input data
	 */
	public function ajax_parse_input() {
		check_ajax_referer( 'mkz-bulk-variations', 'nonce' );

		$input      = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( empty( $input ) ) {
			wp_send_json_error( array( 'message' => __( 'Input data is empty.', 'mkz-bulk-variations' ) ) );
		}

		if ( empty( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Product ID is required.', 'mkz-bulk-variations' ) ) );
		}

		$parser = new Parser();
		$result = $parser->parse_input( $input, $product_id );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'errors' => $result['errors'] ) );
		}

		// Validate variations.
		$validator         = new Validator();
		$validation_result = $validator->validate_variations( $result['data'], $product_id );

		// Get attribute summary.
		$unique_terms = $parser->get_unique_attribute_terms( $result['data'] );

		// Prepare response data.
		$variations_preview = array();
		foreach ( $result['data'] as $variation ) {
			$variations_preview[] = $variation->to_array();
		}

		$attributes_preview = array();
		foreach ( $unique_terms as $attr_name => $terms ) {
			$attributes_preview[] = array(
				'name'  => $attr_name,
				'terms' => $terms,
				'count' => count( $terms ),
			);
		}

		wp_send_json_success(
			array(
				'variations' => $variations_preview,
				'attributes' => $attributes_preview,
				'summary'    => sprintf(
					/* translators: 1: variation count, 2: attribute count */
					__( '%1$d variations and %2$d attributes will be imported.', 'mkz-bulk-variations' ),
					count( $result['data'] ),
					count( $unique_terms )
				),
			)
		);
	}

	/**
	 * AJAX: Import variations
	 */
	public function ajax_import_variations() {
		check_ajax_referer( 'mkz-bulk-variations', 'nonce' );

		$input      = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( empty( $input ) || empty( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'mkz-bulk-variations' ) ) );
		}

		// Wrap in try-catch for better error handling.
		try {
			// Parse input.
			$parser        = new Parser();
			$parse_result  = $parser->parse_input( $input, $product_id );

			if ( ! $parse_result['success'] ) {
				wp_send_json_error( 
					array( 
						'message' => __( 'Failed to parse input data.', 'mkz-bulk-variations' ),
						'errors'  => $parse_result['errors'],
					) 
				);
			}

			// Validate variations.
			$validator         = new Validator();
			$validation_result = $validator->validate_variations( $parse_result['data'], $product_id );

			// Log the attempt.
			$log_id = Database::insert_log(
				$product_id,
				'pending',
				array(
					'input'      => $input,
					'variations' => count( $parse_result['data'] ),
				)
			);

			// Import variations.
			$importer      = new Importer( $product_id );
			$import_result = $importer->import_variations( $parse_result['data'] );

			// Update log.
			$log_status = $import_result['success'] ? 'success' : 'error';
			Database::update_log( $log_id, $log_status, $import_result );

			if ( $import_result['success'] ) {
			$created_count = count( $import_result['created'] );
			$updated_count = count( $import_result['updated'] );

			$message_parts = array();
			if ( $created_count > 0 ) {
				$message_parts[] = sprintf(
					/* translators: %d: number of variations created */
					_n( 'Created %d variation', 'Created %d variations', $created_count, 'mkz-bulk-variations' ),
					$created_count
				);
			}
			if ( $updated_count > 0 ) {
				$message_parts[] = sprintf(
					/* translators: %d: number of variations updated */
					_n( 'Updated %d variation', 'Updated %d variations', $updated_count, 'mkz-bulk-variations' ),
					$updated_count
				);
			}

			$message = implode( '. ', $message_parts ) . '.';

			// Add conversion notice if product was converted.
			if ( ! empty( $import_result['converted'] ) ) {
				$message .= ' ' . __( 'The product was automatically converted to a variable product.', 'mkz-bulk-variations' );
			}

			wp_send_json_success(
				array(
					'message'   => $message,
					'created'   => $import_result['created'],
					'updated'   => $import_result['updated'],
					'converted' => ! empty( $import_result['converted'] ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Import failed.', 'mkz-bulk-variations' ),
					'errors'  => $import_result['errors'],
				)
			);
		}
		} catch ( \Exception $e ) {
			// Log the exception for debugging.
			error_log( 'Bulk Variations Import Error: ' . $e->getMessage() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );
			
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred during import.', 'mkz-bulk-variations' ),
					'errors'  => array( $e->getMessage() ),
				)
			);
		}
	}
}
