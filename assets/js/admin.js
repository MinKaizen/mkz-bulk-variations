/**
 * Bulk Variations Admin JavaScript
 */

(function($) {
	'use strict';

	const BulkVariations = {
		init: function() {
			this.cacheElements();
			this.bindEvents();
			this.initSelect2();
		},

	cacheElements: function() {
		this.$productSearch = $('#mkz-product-search');
		this.$fileUpload = $('#mkz-file-upload');
		this.$inputData = $('#mkz-input-data');
		this.$btnPreview = $('#mkz-btn-preview');
		this.$btnClear = $('#mkz-btn-clear');
		this.$btnImport = $('#mkz-btn-import');
		this.$previewSection = $('#mkz-preview-section');
		this.$previewSummary = $('#mkz-preview-summary');
		this.$previewErrors = $('#mkz-preview-errors');
		this.$variationsTable = $('#mkz-variations-table');
		this.$variationsHeader = $('#mkz-variations-header');
		this.$variationsBody = $('#mkz-variations-body');
		this.$attributesBody = $('#mkz-attributes-body');
		this.$analysisBody = $('#mkz-analysis-body');
		this.$resultsSection = $('#mkz-results-section');
		this.$resultsContent = $('#mkz-results-content');
		this.previewData = null;
	},

		bindEvents: function() {
			this.$fileUpload.on('change', this.handleFileUpload.bind(this));
			this.$btnPreview.on('click', this.handlePreview.bind(this));
			this.$btnClear.on('click', this.handleClear.bind(this));
			this.$btnImport.on('click', this.handleImport.bind(this));
		},

		initSelect2: function() {
			this.$productSearch.select2({
				ajax: {
					url: mkzBulkVariations.ajaxUrl,
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							action: 'mkz_bulk_variations_search_products',
							nonce: mkzBulkVariations.nonce,
							q: params.term
						};
					},
					processResults: function(data) {
						return {
							results: data.results || []
						};
					},
					cache: true
				},
				minimumInputLength: 2,
				placeholder: mkzBulkVariations.strings.selectProduct,
				allowClear: true
			});
		},

	handleFileUpload: function(e) {
		const file = e.target.files[0];
		if (!file) return;

		const reader = new FileReader();
		reader.onload = (event) => {
			this.$inputData.val(event.target.result);
		};
		reader.readAsText(file);
	},

	handlePreview: function() {
		const inputData = this.$inputData.val().trim();
		const productId = this.$productSearch.val();

		// Hide results section
		this.$resultsSection.slideUp();

		// Validation
		if (!inputData) {
			this.showError('Please paste or upload data.');
			return;
		}

		if (!productId) {
			this.showError('Please select a product first.');
			return;
		}

		// Show loading
		this.$btnPreview.addClass('mkz-loading').text(mkzBulkVariations.strings.processing);

		// AJAX request
		$.ajax({
			url: mkzBulkVariations.ajaxUrl,
			type: 'POST',
			data: {
				action: 'mkz_bulk_variations_parse_input',
				nonce: mkzBulkVariations.nonce,
				input: inputData,
				product_id: productId
			},
			success: (response) => {
				this.$btnPreview.removeClass('mkz-loading').text('Preview');

				if (response.success) {
					this.previewData = response.data;
					this.renderPreview(response.data);
					this.$previewSection.slideDown();
				} else {
					this.showErrors(response.data.errors);
				}
			},
			error: () => {
				this.$btnPreview.removeClass('mkz-loading').text('Preview');
				this.showError('Failed to parse input data.');
			}
		});
	},

	handleClear: function() {
		this.$inputData.val('');
		this.$fileUpload.val('');
		this.$previewSection.slideUp();
		this.$resultsSection.slideUp();
		this.previewData = null;
	},

	handleImport: function() {
		const inputData = this.$inputData.val().trim();
		const productId = this.$productSearch.val();

		if (!inputData || !productId) {
			this.showError('Please provide input data and select a product.');
			return;
		}

		if (!confirm('Are you sure you want to import these variations?')) {
			return;
		}

		// Hide previous results
		this.$resultsSection.slideUp();

		// Show loading
		this.$btnImport.addClass('mkz-loading').text(mkzBulkVariations.strings.processing);

		// AJAX request
		$.ajax({
			url: mkzBulkVariations.ajaxUrl,
			type: 'POST',
			data: {
				action: 'mkz_bulk_variations_import',
				nonce: mkzBulkVariations.nonce,
				input: inputData,
				product_id: productId
			},
			success: (response) => {
				this.$btnImport.removeClass('mkz-loading').text('Import Variations');

				if (response.success) {
					this.showImportSuccess(response.data);
				} else {
					this.showImportError(response.data);
				}
			},
			error: (xhr, status, error) => {
				this.$btnImport.removeClass('mkz-loading').text('Import Variations');
				this.showImportError({
					message: 'An unexpected error occurred during import.',
					errors: [error || 'Unknown error'],
					debug: {
						status: xhr.status,
						statusText: xhr.statusText,
						responseText: xhr.responseText
					}
				});
			}
		});
	},

		renderPreview: function(data) {
			// Clear previous preview
			this.$previewSummary.empty();
			this.$previewErrors.empty().hide();
			this.$variationsHeader.empty();
			this.$variationsBody.empty();
			this.$attributesBody.empty();
			this.$analysisBody.empty();

			// Show summary
			this.$previewSummary.html(data.summary);

			// Render variations table
			if (data.variations && data.variations.length > 0) {
				this.renderVariationsTable(data.variations);
			}

			// Render attributes table
			if (data.attributes && data.attributes.length > 0) {
				this.renderAttributesTable(data.attributes);
			}

			// Render analysis table
			if (data.analysis) {
				this.renderAnalysisTable(data.analysis);
			}
		},

		renderVariationsTable: function(variations) {
			// Build headers
			const firstVar = variations[0];
			let headers = '<th>Row</th>';
			
			if (firstVar.sku !== null && firstVar.sku !== undefined) {
				headers += '<th>SKU</th>';
			}
			
			headers += '<th>Price</th>';
			
			// Add attribute headers
			for (const attrName in firstVar.attributes) {
				headers += `<th>${this.escapeHtml(attrName)}</th>`;
			}
			
			headers += '<th>Status</th>';
			this.$variationsHeader.html(headers);

			// Build rows
			variations.forEach((variation) => {
				let rowClass = variation.errors && variation.errors.length > 0 ? 'mkz-error' : '';
				let row = `<tr class="${rowClass}">`;
				row += `<td>${variation.row_number}</td>`;
				
				if (firstVar.sku !== null && firstVar.sku !== undefined) {
					row += `<td>${this.escapeHtml(variation.sku || '')}</td>`;
				}
				
				row += `<td>$${parseFloat(variation.price).toFixed(2)}</td>`;
				
				// Add attribute values
				for (const attrName in variation.attributes) {
					row += `<td>${this.escapeHtml(variation.attributes[attrName])}</td>`;
				}
				
				// Add status
				if (variation.errors && variation.errors.length > 0) {
					row += `<td><span style="color: var(--mkz-error);">❌ Error</span></td>`;
				} else {
					row += `<td><span style="color: var(--mkz-success);">✓ Valid</span></td>`;
				}
				
				row += '</tr>';
				this.$variationsBody.append(row);
			});
		},

		renderAttributesTable: function(attributes) {
			attributes.forEach((attr) => {
				const row = `
					<tr>
						<td>${this.escapeHtml(attr.name)}</td>
						<td>${attr.terms.map(t => this.escapeHtml(t)).join(', ')}</td>
						<td>${attr.count}</td>
					</tr>
				`;
				this.$attributesBody.append(row);
			});
		},

		renderAnalysisTable: function(analysis) {
			// Variations row
			const variationsRow = `
				<tr>
					<td><strong>Variations</strong></td>
					<td>${analysis.variations.new}</td>
					<td>${analysis.variations.update}</td>
					<td>${analysis.variations.unchanged}</td>
				</tr>
			`;
			this.$analysisBody.append(variationsRow);

			// Attributes row
			const attributesRow = `
				<tr>
					<td><strong>Attributes</strong></td>
					<td>${analysis.attributes.new}</td>
					<td>${analysis.attributes.update}</td>
					<td>${analysis.attributes.unchanged}</td>
				</tr>
			`;
			this.$analysisBody.append(attributesRow);
		},

	showErrors: function(errors) {
		if (!errors || errors.length === 0) return;

		let html = '<strong>Errors:</strong><ul>';
		errors.forEach((error) => {
			html += `<li>${this.escapeHtml(error)}</li>`;
		});
		html += '</ul>';

		this.$previewErrors.html(html).slideDown();
	},

	showError: function(message) {
		let html = `<div class="mkz-bv-alert mkz-bv-alert-error">
			<strong>Error:</strong> ${this.escapeHtml(message)}
		</div>`;
		
		this.$resultsContent.html(html);
		this.$resultsSection.slideDown();
		
		// Scroll to results
		$('html, body').animate({
			scrollTop: this.$resultsSection.offset().top - 50
		}, 500);
	},

	showImportSuccess: function(data) {
		const createdCount = data.created ? data.created.length : 0;
		const updatedCount = data.updated ? data.updated.length : 0;
		const unchangedCount = data.unchanged ? data.unchanged.length : 0;

		let html = `<div class="mkz-bv-alert mkz-bv-alert-success">
			<h3 style="margin: 0 0 0.5rem 0;">✓ Import Successful</h3>
			<p style="margin: 0 0 1rem 0;">${this.escapeHtml(data.message)}</p>
		</div>`;

		// Summary table
		if (createdCount > 0 || updatedCount > 0 || unchangedCount > 0) {
			html += `<div class="mkz-bv-table-container">
				<h3>Summary</h3>
				<table class="mkz-bv-table" style="max-width: 500px;">
					<thead>
						<tr>
							<th>Status</th>
							<th style="text-align: center;">Count</th>
						</tr>
					</thead>
					<tbody>`;

			if (createdCount > 0) {
				html += `<tr>
					<td><strong>Created</strong></td>
					<td style="text-align: center; color: var(--mkz-success);">${createdCount}</td>
				</tr>`;
			}

			if (updatedCount > 0) {
				html += `<tr>
					<td><strong>Updated</strong></td>
					<td style="text-align: center; color: var(--mkz-info);">${updatedCount}</td>
				</tr>`;
			}

			if (unchangedCount > 0) {
				html += `<tr>
					<td><strong>Unchanged</strong></td>
					<td style="text-align: center; color: var(--mkz-text-secondary);">${unchangedCount}</td>
				</tr>`;
			}

			html += `</tbody></table></div>`;
		}

		// Show variation IDs if available
		if (createdCount > 0) {
			html += `<div class="mkz-bv-details">
				<h4>Created Variations</h4>
				<p>Variation IDs: ${data.created.join(', ')}</p>
			</div>`;
		}

		if (updatedCount > 0) {
			html += `<div class="mkz-bv-details">
				<h4>Updated Variations</h4>
				<p>Variation IDs: ${data.updated.join(', ')}</p>
			</div>`;
		}

		// Action buttons
		html += `<div style="margin-top: 1.5rem;">
			<a href="post.php?post=${this.$productSearch.val()}&action=edit" class="button button-primary">
				View Product
			</a>
			<button type="button" id="mkz-btn-new-import" class="button">
				Import More Variations
			</button>
		</div>`;

		this.$resultsContent.html(html);
		this.$resultsSection.slideDown();

		// Bind new import button
		$('#mkz-btn-new-import').on('click', () => {
			this.handleClear();
		});

		// Scroll to results
		$('html, body').animate({
			scrollTop: this.$resultsSection.offset().top - 50
		}, 500);
	},

	showImportError: function(data) {
		let html = `<div class="mkz-bv-alert mkz-bv-alert-error">
			<h3 style="margin: 0 0 0.5rem 0;">✗ Import Failed</h3>
			<p style="margin: 0;">${this.escapeHtml(data.message || 'An error occurred during import.')}</p>
		</div>`;

		// Show errors if available
		if (data.errors && data.errors.length > 0) {
			html += `<div class="mkz-bv-details">
				<h4>Errors</h4>
				<ul style="margin: 0; padding-left: 1.5rem; color: var(--mkz-error);">`;
			
			data.errors.forEach((error) => {
				html += `<li>${this.escapeHtml(error)}</li>`;
			});
			
			html += `</ul></div>`;
		}

		// Show debug info if available
		if (data.debug) {
			html += `<details style="margin-top: 1rem; padding: 1rem; background: var(--mkz-bg-secondary); border-radius: var(--mkz-radius);">
				<summary style="cursor: pointer; font-weight: 600; margin-bottom: 0.5rem;">Debug Information</summary>
				<pre style="margin: 0; overflow-x: auto; font-size: 0.75rem; white-space: pre-wrap;">${this.escapeHtml(JSON.stringify(data.debug, null, 2))}</pre>
			</details>`;
		}

		html += `<div style="margin-top: 1.5rem;">
			<button type="button" id="mkz-btn-try-again" class="button button-primary">
				Try Again
			</button>
		</div>`;

		this.$resultsContent.html(html);
		this.$resultsSection.slideDown();

		// Bind try again button
		$('#mkz-btn-try-again').on('click', () => {
			this.$resultsSection.slideUp();
		});

		// Scroll to results
		$('html, body').animate({
			scrollTop: this.$resultsSection.offset().top - 50
		}, 500);
	},

		escapeHtml: function(text) {
			if (text === null || text === undefined) return '';
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, (m) => map[m]);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		BulkVariations.init();
	});

})(jQuery);
