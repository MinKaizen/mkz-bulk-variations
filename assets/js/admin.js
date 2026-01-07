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
			this.$messageContainer = $('#mkz-message-container');
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
				this.showToast(mkzBulkVariations.strings.fileUploaded || 'File uploaded successfully', 'success');
			};
			reader.readAsText(file);
		},

		handlePreview: function() {
			const inputData = this.$inputData.val().trim();
			const productId = this.$productSearch.val();

			// Validation
			if (!inputData) {
				this.showToast(mkzBulkVariations.strings.emptyInput, 'error');
				return;
			}

			if (!productId) {
				this.showToast(mkzBulkVariations.strings.selectProductFirst, 'error');
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
					this.showToast(mkzBulkVariations.strings.parseError, 'error');
				}
			});
		},

		handleClear: function() {
			this.$inputData.val('');
			this.$fileUpload.val('');
			this.$previewSection.slideUp();
			this.previewData = null;
		},

		handleImport: function() {
			const inputData = this.$inputData.val().trim();
			const productId = this.$productSearch.val();

			if (!inputData || !productId) {
				this.showToast(mkzBulkVariations.strings.emptyInput, 'error');
				return;
			}

			if (!confirm('Are you sure you want to import these variations?')) {
				return;
			}

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
						this.showToast(response.data.message, 'success');
						// Clear form after success
						setTimeout(() => {
							this.handleClear();
							// Reload page to show updated product
							window.location.reload();
						}, 2000);
					} else {
						this.showToast(response.data.message || mkzBulkVariations.strings.importError, 'error');
						if (response.data.errors) {
							this.showErrors(response.data.errors);
						}
					}
				},
				error: () => {
					this.$btnImport.removeClass('mkz-loading').text('Import Variations');
					this.showToast(mkzBulkVariations.strings.importError, 'error');
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

		showToast: function(message, type) {
			const toastClass = type === 'success' ? 'mkz-toast-success' : 'mkz-toast-error';
			const toast = $(`<div class="mkz-toast ${toastClass}">${this.escapeHtml(message)}</div>`);
			
			this.$messageContainer.append(toast);
			
			setTimeout(() => {
				toast.fadeOut(() => toast.remove());
			}, 5000);
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
