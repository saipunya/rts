@echo off
setlocal
cd /d "%~dp0"
if exist "dist\RTS-Demo-Setup-x64.exe" (
  start "" "dist\RTS-Demo-Setup-x64.exe"
) else (
  echo Installer not found. See windows\README-build.md to build the package.
  pause
  exit /b 1
)
