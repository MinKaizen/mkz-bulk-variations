# Quick Start Guide

## Installation

1. **Activate the plugin** in WordPress:
   - Go to Plugins > Installed Plugins
   - Find "Bulk Variations"
   - Click "Activate"

2. **Ensure WooCommerce is active** (required dependency)

## First Import

### Step 1: Prepare Your Data

Create a CSV file with at least a **Price** column. Example:

```csv
Package Type,People,Nights,Price
Twin Room,1,5,1275
Twin Room,2,5,1575
King Room,1,5,1275
King Room,2,5,1575
```

**Rules:**
- First row = headers (always)
- **Price** column is required (case-insensitive)
- **SKU** column is optional
- All other columns = product attributes
- Headers will be auto-converted to Proper Case

### Step 2: Access the Tool

**Option A: From Tools Menu**
1. Go to **Tools > Bulk Variations**
2. Search for your variable product
3. Proceed to Step 3

**Option B: From Product Edit Screen**
1. Go to **Products > All Products**
2. Edit a variable product
3. Scroll to Product Data meta box
4. Click **"Bulk Add Variations"** button
5. Proceed to Step 3

### Step 3: Input Data

**Option A: Upload File**
- Click "Choose File"
- Select your CSV/TSV file

**Option B: Paste Data**
- Copy data from Excel/Google Sheets
- Paste into the textarea

### Step 4: Preview

1. Click **"Preview"** button
2. Review the two tables:
   - **Variations Preview**: Shows each variation with all attributes
   - **Attributes Summary**: Shows unique terms per attribute
3. Check for errors (highlighted in red)

### Step 5: Import

1. If preview looks good, click **"Import Variations"**
2. Wait for success message
3. Done! Your variations are created

## Common Issues

### "Missing required column: Price"
- Make sure your CSV has a column named "Price" (case doesn't matter)
- Check for typos: "Prise", "Pricing", etc.

### "Product must be a variable product"
- The selected product must be of type "Variable Product"
- You can change this in Product Data > Product Type dropdown

### "SKU already exists"
- Each SKU must be unique across your store
- Remove the SKU column if you don't need custom SKUs

### Variations created but not visible
- Go to the product edit screen
- Click "Variations" tab
- Click "Expand" to see all variations
- Make sure variations are published (not draft)

## Tips & Tricks

### Excel/Google Sheets
- Select your data range
- Copy (Ctrl+C / Cmd+C)
- Paste directly into the textarea
- Tab-separated format is auto-detected

### Large Datasets
- For 500+ variations, consider splitting into batches
- Import in groups of 100-200 for best performance

### Attribute Reuse
- Existing attributes are automatically reused
- Case doesn't matter: "Twin Room" = "twin room"
- Helps maintain consistency across products

### Updating Existing Variations
- This plugin creates NEW variations only
- To update existing variations, delete them first
- Or manually edit via WooCommerce UI

## Example Use Cases

### Hotel Packages
```csv
Room Type,Occupancy,Duration,Price
Standard,Single,3 nights,299
Standard,Double,3 nights,399
Deluxe,Single,3 nights,499
Deluxe,Double,3 nights,599
```

### Clothing Sizes
```csv
Size,Color,Price
Small,Red,29.99
Small,Blue,29.99
Medium,Red,32.99
Medium,Blue,32.99
Large,Red,35.99
Large,Blue,35.99
```

### Event Tickets
```csv
Ticket Type,Date,Time,Price,SKU
VIP,2024-06-15,Evening,150,VIP-0615-EVE
VIP,2024-06-16,Evening,150,VIP-0616-EVE
General,2024-06-15,Evening,75,GEN-0615-EVE
General,2024-06-16,Evening,75,GEN-0616-EVE
```

## Need Help?

- Check the full README.md for detailed documentation
- Review IMPLEMENTATION_SUMMARY.md for technical details
- Report bugs on the GitHub issue tracker
- Contact support via plugin settings page
