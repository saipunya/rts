@echo off
setlocal
set "PROJ=%~dp0"
set "PHP=C:\xampp\php\php.exe"
set "LIB=%~1"
if "%LIB%"=="" set "LIB=mpdf"

echo Project: %PROJ%
if not exist "%PHP%" (
  echo ERROR: PHP not found at %PHP%
  exit /b 1
)

REM Ensure directories
mkdir "%PROJ%\tmp" 2>nul
mkdir "%PROJ%\fonts" 2>nul

REM Try global Composer
where composer >nul 2>nul
if %errorlevel%==0 (
  pushd "%PROJ%"
  if /i "%LIB%"=="dompdf" (
    call composer require dompdf/dompdf
  ) else (
    call composer update
  )
  popd
) else (
  if not exist "%PROJ%\composer.phar" (
    powershell -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; (New-Object Net.WebClient).DownloadFile('https://getcomposer.org/composer-stable.phar','%PROJ%\\composer.phar')"
  )
  pushd "%PROJ%"
  if /i "%LIB%"=="dompdf" (
    "%PHP%" "%PROJ%\composer.phar" require dompdf/dompdf
  ) else (
    "%PHP%" "%PROJ%\composer.phar" update
  )
  popd
)

if not exist "%PROJ%\vendor\autoload.php" (
  echo ERROR: vendor/autoload.php not found. Composer install failed.
  exit /b 1
) else (
  echo Composer install OK.
)

if /i "%LIB%"=="dompdf" (
  "%PHP%" "%PROJ%\pdf_dompdf_demo.php"
) else (
  "%PHP%" "%PROJ%\ensure_pdf_env.php"
  "%PHP%" "%PROJ%\pdf_th_demo.php"
)

echo Done.
endlocal
