# Design Document: Bulk Variations WordPress Plugin

**Project Name:** Bulk Variations

**PHP Version:** 8.0+

**Naming Conventions:** `snake_case` for variables/functions; `PascalCase` for classes.

**Coding Standard:** Laravel-style formatting; WordPress-style naming.

**Architecture:** Object-Oriented with PSR-4 Namespacing (`BulkVariations\`).

---

## 1. Overview

The **Bulk Variations** plugin is a high-performance tool for WooCommerce store managers to mass-create product variations. It allows users to input data via CSV upload or clipboard pasting (TSV/HTML Tables). The system provides a real-time "Shadcn-style" preview and validation engine to ensure data integrity before any database writes occur.

---

## 2. Functional Specifications

### 2.1 Entry Points

* **Product Edit Screen:** A "Bulk Variations" button is injected into the WooCommerce Product Data meta box. Clicking this redirects to the tool with the `product_id` pre-filled.
* **Global Tools Menu:** Located at `Tools > Bulk Variations`. Includes a Select2/AJAX search bar to locate products by ID, Title, or SKU.

### 2.2 Input Logic & Parsing

* **Format Support:** Detects CSV (comma-separated) and TSV (tab-separated, typical for Excel/Google Sheets).
* **Header Rules:** * The first row is **always** treated as the header row.
* Header detection is **case-insensitive**.
* **Required Column:** A `Price` column must be present. If missing, the data is invalid.


* **Data Transformation:**
* Attribute labels are forced into **Proper Case** (e.g., "package type" becomes "Package Type").
* All values are treated as strings except for the `Price`, which is cast to a float/decimal.
* The `Price` value is mapped to the WooCommerce `regular_price` field.



### 2.3 The Preview Engine

Upon pasting or uploading, a dual-table preview renders:

1. **Variations Preview:** One row per variation showing the SKU (if provided), Price, and Attribute values.
2. **Attributes Summary:** One row per detected attribute showing the unique terms found.
3. **Status Summary:** A message such as *"4 variations and 3 attributes will be imported."*

---

## 3. Technical Architecture

### 3.1 Namespace Strategy

* `BulkVariations\Admin`: Handles menu registration and UI rendering.
* `BulkVariations\Core`: Logic for `Parser`, `Importer`, and `Validator`.
* `BulkVariations\Models`: Data structures for `Variation_Data` and `Log_Entry`.

### 3.2 Database Schema (Import History)

A custom table `{$wpdb->prefix}bulk_variations_logs` tracks every attempt:

* `id`: BigInt (Primary Key).
* `product_id`: BigInt.
* `status`: String (`pending`, `success`, `error`).
* `input_data`: LongText (JSON of raw input).
* `output_data`: LongText (JSON of created IDs or error messages).
* `created_at`: DateTime.

---

## 4. UI/UX Design (Polaris/Shadcn)

The interface uses a modern, clean aesthetic.

* **Isolation:** Assets are enqueued **only** on `tools_page_bulk-variations` and specific product admin pages.
* **Styling:** High contrast, 6px border-radius, `inter` or system sans-serif fonts, and minimalist borders (1px #e2e8f0).
* **Feedback:** Toast notifications for success/error and a progress bar for batch processing.

---

## 5. Parsing Example

**Input Data:**

```csv
Package Type,People,Nights,Price
Twin Room,1,5,1275
Twin Room,2,5,1575
King Room,1,5,1275
King Room,2,5,1575

```

**System Interpretation:**

* **Attributes Identified:** `Package Type`, `People`, `Nights`.
* **Variations Identified:** 4 unique rows.
* **Action:** Creates 4 variations under the target product, assigning the price to the "Regular Price" field.

---

## 6. Edge Cases & Solutions

| Edge Case | Solution |
| --- | --- |
| **Missing Price Column** | System halts and displays a "Missing Required Column: Price" error message. |
| **Existing Attribute Terms** | Validator performs a case-insensitive lookup. If "Twin Room" exists as "twin room", the system maps to the existing term ID. |
| **Large Datasets** | Processing uses an AJAX-based batching system (10 variations per request) to prevent `max_execution_time` timeouts. |
| **Malformed CSV** | If a row has fewer columns than the header, that specific row is highlighted in red in the preview table. |

---

## 7. Development & Build Process

### 7.1 Build Script (`bin/build.sh`)

This script automates the creation of a production-ready `.zip` file, excluding development overhead.

```bash
#!/bin/bash
# 1. Clean dist
rm -rf ./dist && mkdir ./dist

# 2. Install production dependencies
composer install --no-dev --optimize-autoloader

# 3. Minify/Build assets
npm run build

# 4. Copy plugin files to dist/bulk-variations
mkdir -p ./dist/bulk-variations
cp -r ./src ./dist/bulk-variations/
cp -r ./vendor ./dist/bulk-variations/
cp -r ./assets ./dist/bulk-variations/
cp ./bulk-variations.php ./dist/bulk-variations/

# 5. Zip the folder
cd dist
zip -r bulk-variations.zip bulk-variations/

# 6. Reinstall dev dependencies for local work
cd ..
composer install

```

### 7.2 Unit Testing

Tests reside in `tests/Unit/` and use PHPUnit. They must run without the WordPress environment (Mocking `wpdb` and `WC` global constants if needed).

**Example Test:**

```php
public function test_it_identifies_proper_case_attributes(): void
{
    $parser = new \BulkVariations\Core\Parser();
    $raw = "package type,price\nTwin Room,100";
    $result = $parser->parse_input($raw);

    // Asserts the header was converted from 'package type' to 'Package Type'
    $this->assertArrayHasKey('Package Type', $result[0]['attributes']);
}

```

---

## 8. Implementation Roadmap

1. **Phase 1:** Setup PSR-4 structure and Logger database table.
2. **Phase 2:** Implement `Parser` and `Validator` logic with Unit Tests.
3. **Phase 3:** Create Shadcn-inspired Admin UI and AJAX Search.
4. **Phase 4:** Develop `Importer` with WooCommerce CRUD integration.
5. **Phase 5:** Finalize Build Script and Documentation.

**Next Step:** Would you like me to generate the specific PHP class for the `Parser` that handles the Proper Case conversion and Price validation logic?
