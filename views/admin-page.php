<?php
/**
 * Admin Page Template
 *
 * @package BulkVariations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap mkz-bulk-variations">
	<h1><?php esc_html_e( 'Bulk Variations', 'mkz-bulk-variations' ); ?></h1>
	
	<div class="mkz-bv-container">
		<!-- Product Selection Section -->
		<div class="mkz-bv-card">
			<h2><?php esc_html_e( 'Select Product', 'mkz-bulk-variations' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Search for a variable product to add variations to.', 'mkz-bulk-variations' ); ?>
			</p>
			
			<div class="mkz-bv-field">
				<label for="mkz-product-search">
					<?php esc_html_e( 'Product', 'mkz-bulk-variations' ); ?>
				</label>
				<select id="mkz-product-search" style="width: 100%; max-width: 500px;">
					<?php if ( $product_id ) : ?>
						<?php
						$product = wc_get_product( $product_id );
						if ( $product ) :
							?>
							<option value="<?php echo esc_attr( $product_id ); ?>" selected>
								<?php echo esc_html( $product->get_name() . ' (#' . $product_id . ')' ); ?>
							</option>
						<?php endif; ?>
					<?php else : ?>
						<option value=""><?php esc_html_e( 'Select a product...', 'mkz-bulk-variations' ); ?></option>
					<?php endif; ?>
				</select>
			</div>
		</div>

		<!-- Input Section -->
		<div class="mkz-bv-card">
			<h2><?php esc_html_e( 'Input Data', 'mkz-bulk-variations' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Paste CSV/TSV data or upload a file. First row is always treated as headers. Price column is required.', 'mkz-bulk-variations' ); ?>
			</p>
			
			<div class="mkz-bv-field">
				<label for="mkz-file-upload">
					<?php esc_html_e( 'Upload CSV File (Optional)', 'mkz-bulk-variations' ); ?>
				</label>
				<input type="file" id="mkz-file-upload" accept=".csv,.tsv,.txt" />
			</div>

			<div class="mkz-bv-field">
				<label for="mkz-input-data">
					<?php esc_html_e( 'Or Paste Data', 'mkz-bulk-variations' ); ?>
				</label>
				<textarea 
					id="mkz-input-data" 
					rows="10" 
					placeholder="<?php esc_attr_e( 'Package Type,People,Nights,Price&#10;Twin Room,1,5,1275&#10;Twin Room,2,5,1575&#10;King Room,1,5,1275&#10;King Room,2,5,1575', 'mkz-bulk-variations' ); ?>"
				></textarea>
			</div>

			<div class="mkz-bv-actions">
				<button type="button" id="mkz-btn-preview" class="button button-primary">
					<?php esc_html_e( 'Preview', 'mkz-bulk-variations' ); ?>
				</button>
				<button type="button" id="mkz-btn-clear" class="button">
					<?php esc_html_e( 'Clear', 'mkz-bulk-variations' ); ?>
				</button>
			</div>
		</div>

		<!-- Preview Section -->
		<div id="mkz-preview-section" class="mkz-bv-card" style="display: none;">
			<h2><?php esc_html_e( 'Preview', 'mkz-bulk-variations' ); ?></h2>
			
			<div id="mkz-preview-summary" class="mkz-bv-alert mkz-bv-alert-info"></div>

			<div id="mkz-preview-errors" class="mkz-bv-alert mkz-bv-alert-error" style="display: none;"></div>

			<!-- Variations Preview Table -->
			<div class="mkz-bv-table-container">
				<h3><?php esc_html_e( 'Variations', 'mkz-bulk-variations' ); ?></h3>
				<table id="mkz-variations-table" class="mkz-bv-table">
					<thead>
						<tr id="mkz-variations-header"></tr>
					</thead>
					<tbody id="mkz-variations-body"></tbody>
				</table>
			</div>

		<!-- Attributes Preview Table -->
		<div class="mkz-bv-table-container">
			<h3><?php esc_html_e( 'Attributes Summary', 'mkz-bulk-variations' ); ?></h3>
			<table id="mkz-attributes-table" class="mkz-bv-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute Name', 'mkz-bulk-variations' ); ?></th>
						<th><?php esc_html_e( 'Terms', 'mkz-bulk-variations' ); ?></th>
						<th><?php esc_html_e( 'Count', 'mkz-bulk-variations' ); ?></th>
					</tr>
				</thead>
				<tbody id="mkz-attributes-body"></tbody>
			</table>
		</div>

		<!-- Analysis Table -->
		<div class="mkz-bv-table-container">
			<h3><?php esc_html_e( 'Import Analysis', 'mkz-bulk-variations' ); ?></h3>
			<table id="mkz-analysis-table" class="mkz-bv-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'mkz-bulk-variations' ); ?></th>
						<th><?php esc_html_e( 'New', 'mkz-bulk-variations' ); ?></th>
						<th><?php esc_html_e( 'Update', 'mkz-bulk-variations' ); ?></th>
						<th><?php esc_html_e( 'Unchanged', 'mkz-bulk-variations' ); ?></th>
					</tr>
				</thead>
				<tbody id="mkz-analysis-body"></tbody>
			</table>
		</div>

		<div class="mkz-bv-actions">
			<button type="button" id="mkz-btn-import" class="button button-primary button-large">
				<?php esc_html_e( 'Import Variations', 'mkz-bulk-variations' ); ?>
			</button>
		</div>
		</div>

		<!-- Success/Error Messages -->
		<div id="mkz-message-container"></div>
	</div>
</div>
