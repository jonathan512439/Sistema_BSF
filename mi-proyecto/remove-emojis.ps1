# Script para eliminar emojis de archivos Vue
$projectRoot = "d:\Jonathan\Desktop\INFORMATICA\INF3811-ISW2\BSF-docs\mi-proyecto"
$jsPath = "$projectRoot\resources\js"

Write-Host "Procesando archivos Vue..." -ForegroundColor Cyan

# Emojis a eliminar (simplemente los borramos)
$emojisToRemove = @(
    '📄', '📁', '📋', '📊', '📈', '📉', '🔒', '🔓', '🔍', '✅', '❌', '⚠️',
    '✓', '✔️', '✗', '⏳', '⏰', '👤', '👥', '🚀', '💾', '✏️', '🗑️', '🎯',
    '📝', '📱', '💻', '🖥️', '🔥', '☀️', '❄️', '⬇️', '⚙️'
)

$vueFiles = Get-ChildItem -Path $jsPath -Filter "*.vue" -Recurse
$filesModified = 0

foreach ($file in $vueFiles) {
    $content = Get-Content -Path $file.FullName -Raw -Encoding UTF8
    $modified = $false
    
    foreach ($emoji in $emojisToRemove) {
        if ($content.Contains($emoji)) {
            $content = $content.Replace($emoji, '')
            $modified = $true
        }
    }
    
    if ($modified) {
        Set-Content -Path $file.FullName -Value $content -Encoding UTF8 -NoNewline
        $filesModified++
        Write-Host "  OK: $($file.Name)" -ForegroundColor Green
    }
}

Write-Host "`nArchivos modificados: $filesModified" -ForegroundColor Cyan
