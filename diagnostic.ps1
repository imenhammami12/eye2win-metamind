Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   DIAGNOSTIC RECONNAISSANCE FACIALE    ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Cyan

Write-Host "`n1️⃣ FICHIERS" -ForegroundColor Yellow
Test-Path "src/EventSubscriber/FaceAuthSubscriber.php"
Test-Path "src/Controller/FaceAuthController.php"

Write-Host "`n2️⃣ ROUTES" -ForegroundColor Yellow
php bin/console debug:router | Select-String "face"

Write-Host "`n3️⃣ LOGS" -ForegroundColor Yellow
Get-Content var/log/dev.log -Tail 30 -ErrorAction SilentlyContinue

Write-Host "`n4️⃣ CACHE" -ForegroundColor Yellow
php bin/console cache:clear

Write-Host "`nFIN" -ForegroundColor Green