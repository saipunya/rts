param(
  [switch]$RunDemo
)

$proj = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = "C:\xampp\php\php.exe"

Write-Host "Project: $proj"
if (-not (Test-Path $php)) {
  Write-Host "ERROR: PHP not found at $php. Install XAMPP or adjust path." -ForegroundColor Red
  exit 1
}

# Ensure directories
@('tmp','fonts') | ForEach-Object {
  $p = Join-Path $proj $_
  if (-not (Test-Path $p)) { New-Item -ItemType Directory -Force -Path $p | Out-Null }
}

# Resolve composer
$composerCmd = Get-Command composer -ErrorAction SilentlyContinue
$usePhar = $false
if ($composerCmd) {
  Write-Host "Using global Composer: $($composerCmd.Path)"
} else {
  $phar = Join-Path $proj "composer.phar"
  if (-not (Test-Path $phar)) {
    Write-Host "Downloading composer.phar..."
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest "https://getcomposer.org/composer-stable.phar" -OutFile $phar
  }
  $usePhar = $true
}

# Install/update dependencies
Push-Location $proj
try {
  if ($usePhar) {
    & $php "$proj\composer.phar" update
  } else {
    & composer update
  }
} finally {
  Pop-Location
}

# Verify install
$autoload = Join-Path $proj "vendor\autoload.php"
if (-not (Test-Path $autoload)) {
  Write-Host "ERROR: vendor/autoload.php not found. Composer install failed." -ForegroundColor Red
  exit 1
} else {
  Write-Host "Composer install OK."
}

# Environment check
& $php (Join-Path $proj "ensure_pdf_env.php")

# Optional demo
if ($RunDemo) {
  & $php (Join-Path $proj "pdf_th_demo.php")
}

Write-Host "Done."
