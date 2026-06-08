# ============================================================
# 1wellness Cleanup Script — removes residual OJG references
# ============================================================

$base = "c:\Users\Alex\TraeCoder\1wellness"

# 1. Fix tracking.js
$tp = "$base\js\tracking.js"
if (Test-Path $tp) {
    $c = [System.IO.File]::ReadAllText($tp, [System.Text.Encoding]::UTF8)
    $c = $c.Replace("window.OJGTracking", "window.WellnessTracking")
    $c = $c.Replace("OJGTracking", "WellnessTracking")
    $c = $c.Replace("OJG Tracking", "1wellness Tracking")
    $c = $c.Replace("OJG Wellness", "1wellness")
    [System.IO.File]::WriteAllText($tp, $c, [System.Text.Encoding]::UTF8)
    Write-Host "OK: tracking.js" -ForegroundColor Green
}

# 2. Fix all HTML files
$htmlFiles = Get-ChildItem -Path $base -Recurse -Include "*.html"
foreach ($hf in $htmlFiles) {
    $c = [System.IO.File]::ReadAllText($hf.FullName, [System.Text.Encoding]::UTF8)
    $changed = $false

    if ($c.Contains("OJGTracking")) {
        $c = $c.Replace("window.OJGTracking", "window.WellnessTracking")
        $c = $c.Replace("OJGTracking", "WellnessTracking")
        $changed = $true
    }
    if ($c.Contains("ojg_")) {
        $c = $c.Replace("ojg_acne_type", "1w_acne_type")
        $c = $c.Replace("ojg_pcos_type", "1w_pcos_type")
        $c = $c.Replace("ojg_weight_type", "1w_weight_type")
        $c = $c.Replace("ojg_mens_type", "1w_mens_type")
        $c = $c.Replace("ojg_last_order", "1w_last_order")
        $c = $c.Replace("ojg_customer", "1w_customer")
        $c = $c.Replace("ojg_new_user_creds", "1w_new_user_creds")
        $c = $c.Replace("ojg_auto_login", "1w_auto_login")
        $c = $c.Replace("ojg_assessment", "1w_assessment")
        $changed = $true
    }
    if ($c.Contains("ojg.ng")) {
        $c = $c.Replace("https://ojg.ng/egbon-bottle.png", "https://1wellness.club/assets/supplement-bottle.png")
        $c = $c.Replace("@ojg.ng", "@1wellness.club")
        $c = $c.Replace("ojg.ng", "1wellness.club")
        $changed = $true
    }
    if ($c.Contains("OJG Admin")) {
        $c = $c.Replace("OJG Admin", "1wellness Admin")
        $changed = $true
    }

    if ($changed) {
        [System.IO.File]::WriteAllText($hf.FullName, $c, [System.Text.Encoding]::UTF8)
        Write-Host "OK html: $($hf.Name)" -ForegroundColor Green
    }
}

# 3. Fix all PHP files
$phpFiles = Get-ChildItem -Path $base -Recurse -Include "*.php"
foreach ($pf in $phpFiles) {
    $c = [System.IO.File]::ReadAllText($pf.FullName, [System.Text.Encoding]::UTF8)
    $changed = $false

    if ($c.Contains("OJG Admin")) { $c = $c.Replace("OJG Admin", "1wellness Admin"); $changed = $true }
    if ($c.Contains(">OJG<"))     { $c = $c.Replace(">OJG<", ">1W<"); $changed = $true }
    if ($c.Contains("@ojg.ng"))   { $c = $c.Replace("@ojg.ng", "@1wellness.club"); $changed = $true }
    if ($c.Contains("ojg_"))      { $c = $c.Replace("ojg_", "1w_"); $changed = $true }
    if ($c.Contains("OJG Herbal System")) { $c = $c.Replace("OJG Herbal System", "1wellness"); $changed = $true }

    if ($changed) {
        [System.IO.File]::WriteAllText($pf.FullName, $c, [System.Text.Encoding]::UTF8)
        Write-Host "OK php: $($pf.Name)" -ForegroundColor Green
    }
}

# 4. Fix docs.html if it exists
$dh = "$base\backend\docs.html"
if (Test-Path $dh) {
    $c = [System.IO.File]::ReadAllText($dh, [System.Text.Encoding]::UTF8)
    $c = $c.Replace("OJG Herbal API Documentation", "1wellness API Documentation")
    $c = $c.Replace("OJG Herbal API", "1wellness API")
    $c = $c.Replace("OJG Herbal", "1wellness")
    $c = $c.Replace("OJG", "1wellness")
    [System.IO.File]::WriteAllText($dh, $c, [System.Text.Encoding]::UTF8)
    Write-Host "OK: docs.html" -ForegroundColor Green
}

# 5. Final verification — count remaining OJG hits
Write-Host ""
Write-Host "--- Remaining OJG references ---" -ForegroundColor Yellow
$remaining = Get-ChildItem -Path $base -Recurse -Include "*.html","*.php","*.js" |
    Select-String -Pattern "OJG|ojg-wellness|ojgherbal|ojg\.ng|@ojg" |
    Where-Object { $_.Line -notmatch "//.*OJG" }
if ($remaining.Count -eq 0) {
    Write-Host "ZERO remaining OJG references. Rebranding 100% complete!" -ForegroundColor Cyan
} else {
    Write-Host "$($remaining.Count) references remain:" -ForegroundColor Red
    $remaining | ForEach-Object { $fn = $_.Filename; $ln = $_.LineNumber; $ll = $_.Line.Trim(); Write-Host "  ${fn}:${ln} - ${ll}" }
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  Cleanup COMPLETE" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
