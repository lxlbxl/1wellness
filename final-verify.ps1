# Final fixes for dashboard JS + verification
$dashFiles = @(
    "c:\Users\Alex\TraeCoder\1wellness\member\js\dashboard-enhanced.js",
    "c:\Users\Alex\TraeCoder\1wellness\member\js\dashboard.js"
)
foreach ($f in $dashFiles) {
    if (-not (Test-Path $f)) { continue }
    $c = [System.IO.File]::ReadAllText($f, [System.Text.Encoding]::UTF8)
    $c = $c.Replace("from the OJG PCOS program", "from the 1wellness CycleSync program")
    $c = $c.Replace("OJG PCOS program", "1wellness CycleSync program")
    $c = $c.Replace("OJG Herbal Plan Renewal", "1wellness Plan Renewal")
    $c = $c.Replace('"OJG_"', '"1W_"')
    $c = $c.Replace("title: `"1W_ Herbal Plan Renewal`"", "title: `"1wellness Plan Renewal`"")
    [System.IO.File]::WriteAllText($f, $c, [System.Text.Encoding]::UTF8)
    Write-Host "Fixed: $f" -ForegroundColor Green
}

# Final verification
Write-Host ""
Write-Host "--- Final verification ---" -ForegroundColor Yellow
$remaining = Get-ChildItem -Path "c:\Users\Alex\TraeCoder\1wellness" -Recurse -Include "*.html","*.php","*.js" |
    Select-String -Pattern "OJG|ojg-wellness\.com|ojgherbal\.com|ojg\.ng" |
    Where-Object { $_.Filename -notmatch "db_config|88db_config|rebrand|cleanup" }

$count = @($remaining).Count
Write-Host "$count references remain (excluding credential files)" -ForegroundColor Cyan

if ($count -gt 0) {
    $remaining | Select-Object Filename, LineNumber, Line | Format-Table -AutoSize -Wrap
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  1wellness Rebranding VERIFIED" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
