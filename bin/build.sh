#!/bin/bash

# Bulk Variations Build Script
# Creates a production-ready .zip file

set -e

echo "Building Bulk Variations plugin..."

# 1. Clean dist directory
echo "Cleaning dist directory..."
rm -rf ./dist
mkdir -p ./dist

# 2. Install production dependencies
echo "Installing production dependencies..."
composer install --no-dev --optimize-autoloader --quiet

# 3. Build assets (if needed)
echo "Building assets..."
npm run build

# 4. Create plugin directory
echo "Copying plugin files..."
mkdir -p ./dist/mkz-bulk-variations

# Copy necessary files and directories
cp -r ./src ./dist/mkz-bulk-variations/
cp -r ./vendor ./dist/mkz-bulk-variations/
cp -r ./assets ./dist/mkz-bulk-variations/
cp -r ./views ./dist/mkz-bulk-variations/
cp ./mkz-bulk-variations.php ./dist/mkz-bulk-variations/
cp ./readme.txt ./dist/mkz-bulk-variations/

# Copy composer files (optional, for reference)
cp ./composer.json ./dist/mkz-bulk-variations/

# 5. Create zip file
echo "Creating zip file..."
cd dist
zip -r mkz-bulk-variations.zip mkz-bulk-variations/ -q
cd ..

echo "Build complete! File created: dist/mkz-bulk-variations.zip"

# 6. Reinstall dev dependencies for local development
echo "Reinstalling dev dependencies..."
composer install --quiet

echo "Done!"
