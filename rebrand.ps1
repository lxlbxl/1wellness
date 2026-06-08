# ============================================================
# 1wellness Rebranding Script v2
# ============================================================

function Rebrand-File {
    param([string]$FilePath, [hashtable]$Replacements)
    if (-not (Test-Path $FilePath)) {
        Write-Host "SKIP: $FilePath" -ForegroundColor Yellow
        return
    }
    $content = [System.IO.File]::ReadAllText($FilePath, [System.Text.Encoding]::UTF8)
    foreach ($key in $Replacements.Keys) {
        $content = $content.Replace($key, $Replacements[$key])
    }
    [System.IO.File]::WriteAllText($FilePath, $content, [System.Text.Encoding]::UTF8)
    Write-Host "OK: $FilePath" -ForegroundColor Green
}

$base = "c:\Users\Alex\TraeCoder\1wellness"

# ─────────────────────────────────────────────────────────────
# PCOS remaining pages – CycleSync by 1wellness
# ─────────────────────────────────────────────────────────────
$pcosNav = '<div class="flex flex-col"><span class="text-lg font-serif font-bold tracking-tight text-forest-900 leading-none">CycleSync</span><span class="text-[10px] font-sans font-medium tracking-[0.15em] text-terracotta-600 uppercase leading-none mt-0.5">by 1wellness</span></div>'

$pcosFiles = @("results.html","select-plan.html","30-day-plan.html","90-day-plan.html","thank-you.html","digital-plan.html","generating-plan.html","thank-you-pdf.html","sampleUI.html")

foreach ($f in $pcosFiles) {
    $fp = "$base\pcos\$f"
    if (-not (Test-Path $fp)) { Write-Host "SKIP: $f" -ForegroundColor Yellow; continue }
    $c = [System.IO.File]::ReadAllText($fp, [System.Text.Encoding]::UTF8)

    # Nav icon
    $c = $c.Replace('<span class="text-sand font-serif italic text-xl">P</span>', '<span class="text-sand font-bold text-sm">1W</span>')
    $c = $c.Replace('<span class="text-sand font-serif italic text-2xl">P</span>', '<span class="text-sand font-bold text-sm">1W</span>')
    $c = $c.Replace('<span class="text-sand font-serif italic text-lg">P</span>', '<span class="text-sand font-bold text-xs">1W</span>')
    # Nav brand text - various page-specific strings
    $c = $c.Replace('<span class="text-lg font-serif font-bold tracking-tight text-forest-900">PCOS Solutions</span>', $pcosNav)
    $c = $c.Replace('<span class="text-lg font-serif font-bold tracking-tight text-forest-900">PCOS Balance Plan</span>', $pcosNav)
    $c = $c.Replace('<span class="text-lg font-serif font-bold tracking-tight text-forest-900">PCOS Select Plan</span>', $pcosNav)
    $c = $c.Replace('<span class="text-lg font-serif font-bold tracking-tight text-forest-900">PCOS Digital Plan</span>', $pcosNav)
    $c = $c.Replace('<span class="text-lg font-serif font-bold tracking-tight text-forest-900">PCOS Assessment</span>', $pcosNav)
    $c = $c.Replace('<span class="text-lg font-serif font-bold tracking-tight text-forest-900">PCOS Member</span>', $pcosNav)
    $c = $c.Replace('<span class="text-lg font-serif font-bold tracking-tight text-forest-900">PCOS Plan</span>', $pcosNav)
    $c = $c.Replace('<span class="text-lg font-serif font-bold tracking-tight text-forest-900">PCOS Protocol</span>', $pcosNav)
    # Footer
    $c = $c.Replace('PCOS Solutions. Helping women reclaim their health naturally.', '1wellness. CycleSync is a 1wellness brand. Helping women reclaim their health naturally.')
    $c = $c.Replace('PCOS Solutions.', 'CycleSync by 1wellness.')
    $c = $c.Replace('PCOS Solutions', 'CycleSync by 1wellness')
    # Copyright years
    $c = $c.Replace('2024 PCOS', '2026 1wellness')
    $c = $c.Replace('2025 PCOS', '2026 1wellness')
    # Tracking
    $c = $c.Replace('pcos-funnel-', 'cyclesync-pcos-')
    # OJG refs
    $c = $c.Replace('OJG Wellness', '1wellness')
    $c = $c.Replace('OJG Herbal', '1wellness')
    $c = $c.Replace('ojg-wellness.com', '1wellness.club')
    $c = $c.Replace('ojgherbal.com', '1wellness.club')

    [System.IO.File]::WriteAllText($fp, $c, [System.Text.Encoding]::UTF8)
    Write-Host "OK: $f" -ForegroundColor Green
}

# ─────────────────────────────────────────────────────────────
# ACNE funnel – GlowClear by 1wellness
# ─────────────────────────────────────────────────────────────
$acneNav = '<div class="flex flex-col"><span class="text-lg font-serif font-bold tracking-tight text-forest-900 leading-none">GlowClear</span><span class="text-[10px] font-sans font-medium tracking-[0.15em] text-emerald-600 uppercase leading-none mt-0.5">by 1wellness</span></div>'

$acneFiles = @("index.html","assessment.html","results.html","select-plan.html","30-day-plan.html","90-day-plan.html","thank-you.html","digital-plan.html","generating-plan.html","thank-you-pdf.html","sampleUI.html")

foreach ($f in $acneFiles) {
    $fp = "$base\acne\$f"
    if (-not (Test-Path $fp)) { Write-Host "SKIP: $f" -ForegroundColor Yellow; continue }
    $c = [System.IO.File]::ReadAllText($fp, [System.Text.Encoding]::UTF8)

    $c = $c.Replace('<span class="text-sand font-serif italic text-xl">A</span>', '<span class="text-white font-bold text-sm">1W</span>')
    $c = $c.Replace('<span class="text-sand font-serif italic text-2xl">A</span>', '<span class="text-white font-bold text-sm">1W</span>')
    $c = $c.Replace('<span class="text-sand font-serif italic text-lg">A</span>', '<span class="text-white font-bold text-xs">1W</span>')
    $c = $c.Replace("Acne Solutions", "GlowClear")
    $c = $c.Replace("Clear Skin Protocol", "GlowClear Protocol")
    $c = $c.Replace("Acne Balance Plan", "GlowClear Plan")
    $c = $c.Replace("OJG Wellness", "1wellness")
    $c = $c.Replace("OJG Herbal", "1wellness")
    $c = $c.Replace("ojg-wellness.com", "1wellness.club")
    $c = $c.Replace("ojgherbal.com", "1wellness.club")
    $c = $c.Replace("2024 Acne", "2026 1wellness")
    $c = $c.Replace("2025 Acne", "2026 1wellness")
    $c = $c.Replace("acne-funnel-", "glowclear-acne-")
    $c = $c.Replace("GlowClear. Helping", "GlowClear by 1wellness. Helping")

    [System.IO.File]::WriteAllText($fp, $c, [System.Text.Encoding]::UTF8)
    Write-Host "OK acne/$f" -ForegroundColor Green
}

# ─────────────────────────────────────────────────────────────
# WEIGHT funnel – LeanFlow by 1wellness
# ─────────────────────────────────────────────────────────────
$weightFiles = @("index.html","assessment.html","results.html","select-plan.html","30-day-plan.html","90-day-plan.html","thank-you.html","digital-plan.html","generating-plan.html","thank-you-pdf.html","sampleUI.html")

foreach ($f in $weightFiles) {
    $fp = "$base\weight\$f"
    if (-not (Test-Path $fp)) { Write-Host "SKIP: weight/$f" -ForegroundColor Yellow; continue }
    $c = [System.IO.File]::ReadAllText($fp, [System.Text.Encoding]::UTF8)

    $c = $c.Replace('<span class="text-sand font-serif italic text-xl">W</span>', '<span class="text-white font-bold text-sm">1W</span>')
    $c = $c.Replace('<span class="text-sand font-serif italic text-2xl">W</span>', '<span class="text-white font-bold text-sm">1W</span>')
    $c = $c.Replace("Weight Solutions", "LeanFlow")
    $c = $c.Replace("Weight Wellness", "LeanFlow")
    $c = $c.Replace("Weight Balance Plan", "LeanFlow Plan")
    $c = $c.Replace("OJG Wellness", "1wellness")
    $c = $c.Replace("OJG Herbal", "1wellness")
    $c = $c.Replace("ojg-wellness.com", "1wellness.club")
    $c = $c.Replace("ojgherbal.com", "1wellness.club")
    $c = $c.Replace("2024 Weight", "2026 1wellness")
    $c = $c.Replace("2025 Weight", "2026 1wellness")
    $c = $c.Replace("weight-funnel-", "leanflow-weight-")

    [System.IO.File]::WriteAllText($fp, $c, [System.Text.Encoding]::UTF8)
    Write-Host "OK weight/$f" -ForegroundColor Green
}

# ─────────────────────────────────────────────────────────────
# MENS funnel – Vitale by 1wellness
# ─────────────────────────────────────────────────────────────
$mensFiles = @("index.html","assessment.html","results.html","select-plan.html","30-day-plan.html","90-day-plan.html","thank-you.html","digital-plan.html","generating-plan.html","thank-you-pdf.html","sampleUI.html")

foreach ($f in $mensFiles) {
    $fp = "$base\mens\$f"
    if (-not (Test-Path $fp)) { Write-Host "SKIP: mens/$f" -ForegroundColor Yellow; continue }
    $c = [System.IO.File]::ReadAllText($fp, [System.Text.Encoding]::UTF8)

    $c = $c.Replace('<span class="text-sand font-serif italic text-xl">M</span>', '<span class="text-white font-bold text-sm">1W</span>')
    $c = $c.Replace('<span class="text-sand font-serif italic text-2xl">M</span>', '<span class="text-white font-bold text-sm">1W</span>')
    $c = $c.Replace("Men's Solutions", "Vitale")
    $c = $c.Replace("Men's Vitality", "Vitale")
    $c = $c.Replace("Mens Vitality", "Vitale")
    $c = $c.Replace("Mens Solutions", "Vitale")
    $c = $c.Replace("OJG Wellness", "1wellness")
    $c = $c.Replace("OJG Herbal", "1wellness")
    $c = $c.Replace("ojg-wellness.com", "1wellness.club")
    $c = $c.Replace("ojgherbal.com", "1wellness.club")
    $c = $c.Replace("2024 Mens", "2026 1wellness")
    $c = $c.Replace("2025 Mens", "2026 1wellness")
    $c = $c.Replace("mens-funnel-", "vitale-mens-")

    [System.IO.File]::WriteAllText($fp, $c, [System.Text.Encoding]::UTF8)
    Write-Host "OK mens/$f" -ForegroundColor Green
}

# ─────────────────────────────────────────────────────────────
# HUB – main index.html
# ─────────────────────────────────────────────────────────────
$fp = "$base\index.html"
$c = [System.IO.File]::ReadAllText($fp, [System.Text.Encoding]::UTF8)
$c = $c.Replace("OJG Wellness", "1wellness")
$c = $c.Replace("OJG Herbal", "1wellness")
$c = $c.Replace("ojg-wellness.com", "1wellness.club")
$c = $c.Replace("ojgherbal.com", "1wellness.club")
$c = $c.Replace("support@ojgherbal.com", "hello@1wellness.club")
$c = $c.Replace("www.ojgherbal.com", "1wellness.club")
$c = $c.Replace("2025 OJG Herbal.", "2026 1wellness.")
$c = $c.Replace("PCOS Balance", "CycleSync")
$c = $c.Replace("Clear Skin Protocol", "GlowClear")
$c = $c.Replace("Weight Wellness", "LeanFlow")
[System.IO.File]::WriteAllText($fp, $c, [System.Text.Encoding]::UTF8)
Write-Host "OK: index.html (hub)" -ForegroundColor Green

# ─────────────────────────────────────────────────────────────
# MEMBER AREA
# ─────────────────────────────────────────────────────────────
foreach ($f in @("login.html","index.html","login.php")) {
    $fp = "$base\member\$f"
    if (-not (Test-Path $fp)) { Write-Host "SKIP: member/$f" -ForegroundColor Yellow; continue }
    $c = [System.IO.File]::ReadAllText($fp, [System.Text.Encoding]::UTF8)
    $c = $c.Replace("OJG Wellness", "1wellness")
    $c = $c.Replace("OJG Herbal", "1wellness")
    $c = $c.Replace("ojg-wellness.com", "1wellness.club")
    $c = $c.Replace("ojgherbal.com", "1wellness.club")
    $c = $c.Replace("PCOS Member", "1wellness Member")
    $c = $c.Replace("OJG Member", "1wellness Member")
    $c = $c.Replace("OJG Portal", "1wellness Portal")
    [System.IO.File]::WriteAllText($fp, $c, [System.Text.Encoding]::UTF8)
    Write-Host "OK: member/$f" -ForegroundColor Green
}

# ─────────────────────────────────────────────────────────────
# BACKEND PHP files
# ─────────────────────────────────────────────────────────────
$phpFiles = Get-ChildItem -Path "$base\backend" -Recurse -Include "*.php"
foreach ($phpFile in $phpFiles) {
    $c = [System.IO.File]::ReadAllText($phpFile.FullName, [System.Text.Encoding]::UTF8)
    $changed = $false
    $phpSwaps = @(
        @("OJG Wellness",      "1wellness"),
        @("OJG Herbal System", "1wellness"),
        @("OJG Herbal",        "1wellness"),
        @("ojg-wellness.com",  "1wellness.club"),
        @("ojgherbal.com",     "1wellness.club"),
        @("ojg_herbal",        "1wellness"),
        @("OJG_HERBAL",        "ONE_WELLNESS")
    )
    foreach ($pair in $phpSwaps) {
        if ($c.Contains($pair[0])) {
            $c = $c.Replace($pair[0], $pair[1])
            $changed = $true
        }
    }
    if ($changed) {
        [System.IO.File]::WriteAllText($phpFile.FullName, $c, [System.Text.Encoding]::UTF8)
        Write-Host "OK php: $($phpFile.Name)" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  1wellness Full Rebranding COMPLETE" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
