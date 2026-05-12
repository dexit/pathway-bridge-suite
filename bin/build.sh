#!/bin/bash
# Pathway Bridge Suite - Production Build Script

# 1. Cleanup
echo "Cleaning up old builds..."
rm -rf dist/
rm -rf assets/bundle.js assets/style.css

# 2. PHP Dependencies (using Jetpack Autoloader)
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# 3. Frontend Assets
echo "Installing NPM dependencies..."
npm install
echo "Building React dashboard..."
npm run build

# 4. Final ZIP Packaging
echo "Packaging for production..."
mkdir -p dist/pathway-bridge-suite
cp -r includes/ dist/pathway-bridge-suite/
cp -r assets/ dist/pathway-bridge-suite/
cp -r deps/ dist/pathway-bridge-suite/
cp -r vendor/ dist/pathway-bridge-suite/
cp pathway-bridge-suite.php dist/pathway-bridge-suite/
cp README.md dist/pathway-bridge-suite/
cp LICENSE dist/pathway-bridge-suite/ 2>/dev/null || true

cd dist
zip -r pathway-bridge-suite.zip pathway-bridge-suite/
cd ..

echo "Build complete: dist/pathway-bridge-suite.zip"
