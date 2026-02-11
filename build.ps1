Write-Host "============================================" -ForegroundColor Cyan
Write-Host " MainWP AI1WM Manager - Build Script (tar)" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $PSScriptRoot

# Step 1: Delete old ZIPs
Write-Host "[1/4] Deleting old ZIP files..." -ForegroundColor Yellow
@("mainwp-ai1wm-manager.zip", "mainwp-ai1wm-manager-child.zip") | ForEach-Object {
    if (Test-Path $_) {
        Remove-Item $_ -Force
        Write-Host "      Deleted $_" -ForegroundColor Gray
    }
}

# Step 2: Dashboard plugin
Write-Host ""
Write-Host "[2/4] Creating mainwp-ai1wm-manager.zip..." -ForegroundColor Yellow
# Use tar to ensure proper folder structure
try {
    tar -a -c -f mainwp-ai1wm-manager.zip mainwp-ai1wm-manager
    if (Test-Path "mainwp-ai1wm-manager.zip") {
        Write-Host "      Done!" -ForegroundColor Green
    } else {
        Write-Host "      Failed to create ZIP!" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "      Error: $_" -ForegroundColor Red
    exit 1
}

# Step 3: Child plugin
Write-Host ""
Write-Host "[3/4] Creating mainwp-ai1wm-manager-child.zip..." -ForegroundColor Yellow
try {
    tar -a -c -f mainwp-ai1wm-manager-child.zip mainwp-ai1wm-manager-child
    if (Test-Path "mainwp-ai1wm-manager-child.zip") {
        Write-Host "      Done!" -ForegroundColor Green
    } else {
        Write-Host "      Failed to create ZIP!" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "      Error: $_" -ForegroundColor Red
    exit 1
}

# Step 4: Summary
Write-Host ""
Write-Host "[4/4] Build complete!" -ForegroundColor Green
Write-Host ""
Write-Host " Output:" -ForegroundColor Cyan
Write-Host "  - mainwp-ai1wm-manager.zip       (Dashboard)"
Write-Host "  - mainwp-ai1wm-manager-child.zip  (Child sites)"
Write-Host "============================================" -ForegroundColor Cyan
