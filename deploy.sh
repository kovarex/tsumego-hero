#!/bin/bash

git pull

composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction

# Pre-build and minify all CSS/JS assets for production (faster page loads)
# This generates all files in webroot/cache_css/ and webroot/cache_js/
./bin/cake asset_compress build