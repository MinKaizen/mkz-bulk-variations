# Bulk Variations WordPress Plugin

A high-performance tool for WooCommerce store managers to mass-create product variations via CSV upload or clipboard pasting.

## Features

- **Flexible Input**: Support for CSV/TSV files or direct paste from Excel/Google Sheets
- **Smart Parsing**: Automatic delimiter detection (comma or tab-separated)
- **Case Normalization**: Automatically converts attribute names to Proper Case
- **Real-time Preview**: See exactly what will be imported before committing
- **Validation Engine**: Comprehensive data integrity checks before import
- **WooCommerce Integration**: Creates product attributes, terms, and variations using WooCommerce CRUD
- **Import History**: Tracks all import attempts with detailed logs
- **Modern UI**: Clean, Shadcn-inspired design system

## Requirements

- PHP 8.0+
- WordPress 5.8+
- WooCommerce 5.0+

## Installation

1. Download the latest release from the releases page
2. Upload to your WordPress installation via Plugins > Add New > Upload Plugin
3. Activate the plugin through the 'Plugins' menu
4. Go to Tools > Bulk Variations to start using the plugin

## Usage

### From Tools Menu

1. Navigate to **Tools > Bulk Variations**
2. Search and select a variable product
3. Paste your CSV/TSV data or upload a file
4. Click **Preview** to validate the data
5. Review the variations and attributes tables
6. Click **Import Variations** to create the variations

### From Product Edit Screen

1. Edit a variable product
2. Click the **Bulk Add Variations** button in the Product Data meta box
3. Follow the same steps as above

## Input Format

### Required Column

- **Price**: Must be present (case-insensitive)

### Optional Columns

- **SKU**: Product variation SKU (optional)
- **Any other columns**: Treated as product attributes

### Example Input

```csv
Package Type,People,Nights,Price
Twin Room,1,5,1275
Twin Room,2,5,1575
King Room,1,5,1275
King Room,2,5,1575
```

This will create:
- 4 variations
- 3 attributes: Package Type, People, Nights
- Each variation with its corresponding price

## Development

### Setup

```bash
# Install dependencies
composer install
npm install

# Run tests
composer test
```

### Build

```bash
# Create production-ready zip
./bin/build.sh
```

The build script:
1. Installs production dependencies
2. Builds assets
3. Creates a clean zip file in `dist/mkz-bulk-variations.zip`
4. Reinstalls dev dependencies for continued development

## Architecture

### Namespace Structure

- `BulkVariations\Admin`: Admin UI and menu registration
- `BulkVariations\Core`: Business logic (Parser, Validator, Importer, Database)
- `BulkVariations\Models`: Data models (Variation_Data, Log_Entry)

### Key Components

**Parser** (`src/Core/Parser.php`)
- Detects CSV/TSV format
- Normalizes headers to Proper Case
- Validates required Price column
- Extracts attributes and variations

**Validator** (`src/Core/Validator.php`)
- Validates prices
- Checks SKU uniqueness
- Verifies attribute data integrity

**Importer** (`src/Core/Importer.php`)
- Creates WooCommerce attributes
- Creates attribute terms
- Creates product variations
- Handles WooCommerce CRUD operations

**Database** (`src/Core/Database.php`)
- Manages custom log table
- Tracks import history

## Edge Cases Handled

| Edge Case | Solution |
|-----------|----------|
| Missing Price Column | System halts with clear error message |
| Existing Attributes | Case-insensitive lookup, reuses existing attributes |
| Existing Terms | Case-insensitive lookup, reuses existing terms |
| Duplicate SKUs | Validation error prevents import |
| Malformed Rows | Highlighted in preview table with error status |
| Empty Values | Validation error with specific row/column info |

## Database Schema

The plugin creates a custom table `{prefix}_bulk_variations_logs`:

```sql
CREATE TABLE wp_bulk_variations_logs (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  input_data longtext NOT NULL,
  output_data longtext DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY product_id (product_id),
  KEY status (status),
  KEY created_at (created_at)
);
```

## Coding Standards

- **PHP**: Laravel-style formatting, WordPress-style naming
- **Variables/Functions**: `snake_case`
- **Classes**: `PascalCase`
- **Namespacing**: PSR-4 (`BulkVariations\`)

## License

GPL v2 or later

## Support

For issues and feature requests, please use the GitHub issue tracker.
