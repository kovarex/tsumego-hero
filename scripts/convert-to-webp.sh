#!/bin/sh
# Convert all PNG and JPEG images to WebP format
# Usage: composer images:webp
# 
# This script:
# 1. Finds all PNG and JPEG files in webroot/img/
# 2. Creates WebP versions alongside them (skips if WebP is newer than source)
# 3. Preserves original files (for editing/source of truth)
#
# WebP settings:
# - Quality: 80 (good balance of size/quality)
# - Preserves transparency for PNGs

set -e

cd "$(dirname "$0")/.."

IMG_DIR="webroot/img"

if [ ! -d "$IMG_DIR" ]; then
    echo "Error: $IMG_DIR not found"
    exit 1
fi

echo "Converting PNG and JPEG images to WebP..."
echo ""

# Check if ImageMagick/convert is available
if ! command -v convert > /dev/null 2>&1; then
    echo "Error: ImageMagick 'convert' command not found"
    echo "Install with: apt-get install imagemagick"
    exit 1
fi

converted=0
skipped=0
failed=0

echo "=== Processing images ==="
list_file=$(mktemp)
find "$IMG_DIR" \( -name '*.png' -o -name '*.jpg' -o -name '*.jpeg' \) -type f | sort > "$list_file"

while IFS= read -r src_file; do
    # Determine extension and output file
    ext="${src_file##*.}"
    webp_file="${src_file%.$ext}.webp"
    
    # Skip if WebP exists and is NOT older than source
    # Note: Using ! -ot instead of -nt to handle equal timestamps (e.g., after git checkout)
    if [ -f "$webp_file" ] && ! [ "$webp_file" -ot "$src_file" ]; then
        skipped=$((skipped + 1))
        continue
    fi
    
    # Convert to WebP
    if convert "$src_file" -quality 80 "$webp_file" 2>/dev/null; then
        src_size=$(stat -c%s "$src_file" 2>/dev/null || echo 0)
        webp_size=$(stat -c%s "$webp_file" 2>/dev/null || echo 0)
        
        if [ "$src_size" -gt 0 ]; then
            savings=$((100 - (webp_size * 100 / src_size)))
            rel_path="${src_file#$IMG_DIR/}"
            echo "  ok: $rel_path ($savings% smaller)"
        fi
        converted=$((converted + 1))
    else
        rel_path="${src_file#$IMG_DIR/}"
        echo "  failed: $rel_path"
        failed=$((failed + 1))
    fi
done < "$list_file"
rm -f "$list_file"

echo ""
echo "========================================="
echo "Conversion complete!"
echo "  Converted: $converted"
echo "  Skipped (up-to-date): $skipped"
echo "  Failed: $failed"
echo "========================================="

# Show size comparison
png_total=$(find "$IMG_DIR" -name '*.png' -type f -exec stat -c%s {} + 2>/dev/null | awk '{s+=$1} END {print s}')
jpg_total=$(find "$IMG_DIR" \( -name '*.jpg' -o -name '*.jpeg' \) -type f -exec stat -c%s {} + 2>/dev/null | awk '{s+=$1} END {print s}')
webp_total=$(find "$IMG_DIR" -name '*.webp' -type f -exec stat -c%s {} + 2>/dev/null | awk '{s+=$1} END {print s}')

original_total=$((png_total + jpg_total))

if [ -n "$original_total" ] && [ -n "$webp_total" ] && [ "$original_total" -gt 0 ]; then
    orig_mb=$((original_total / 1048576))
    webp_mb=$((webp_total / 1048576))
    savings=$((100 - (webp_total * 100 / original_total)))
    echo ""
    echo "Size comparison:"
    echo "  PNG total:  $((png_total / 1048576))MB"
    echo "  JPEG total: $((jpg_total / 1048576))MB"
    echo "  Original:   ${orig_mb}MB"
    echo "  WebP total: ${webp_mb}MB"
    echo "  Savings:    ${savings}%"
fi
