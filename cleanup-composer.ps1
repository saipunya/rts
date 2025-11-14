Write-Host "Cleaning Composer artifacts..." -ForegroundColor Cyan
if (Test-Path vendor) {
  Remove-Item -Recurse -Force vendor
  Write-Host "Removed vendor/"
} else {
  Write-Host "vendor/ not found"
}
if (Test-Path composer.lock) {
  Remove-Item -Force composer.lock
  Write-Host "Removed composer.lock"
} else {
  Write-Host "composer.lock not found"
}
Write-Host "Running composer install..." -ForegroundColor Cyan
composer install
Write-Host "Done."
