#!/bin/bash

echo "============================================"
echo " MainWP AI1WM Manager - Build Script"
echo "============================================"
echo ""

cd "$(dirname "$0")"

# Step 1: Delete old ZIPs
echo "[1/4] Deleting old ZIP files..."
for file in mainwp-ai1wm-manager.zip mainwp-ai1wm-manager-child.zip; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "      Deleted $file"
    fi
done

# Step 2: Dashboard plugin
echo ""
echo "[2/4] Creating mainwp-ai1wm-manager.zip..."
if zip -r mainwp-ai1wm-manager.zip mainwp-ai1wm-manager -x "*.git*" > /dev/null 2>&1; then
    echo "      Done!"
else
    echo "      Failed to create ZIP!"
    exit 1
fi

# Step 3: Child plugin
echo ""
echo "[3/4] Creating mainwp-ai1wm-manager-child.zip..."
if zip -r mainwp-ai1wm-manager-child.zip mainwp-ai1wm-manager-child -x "*.git*" > /dev/null 2>&1; then
    echo "      Done!"
else
    echo "      Failed to create ZIP!"
    exit 1
fi

# Step 4: Summary
echo ""
echo "[4/4] Build complete!"
echo ""
echo " Output:"
echo "  - mainwp-ai1wm-manager.zip       (Dashboard)"
echo "  - mainwp-ai1wm-manager-child.zip  (Child sites)"
echo "============================================"
