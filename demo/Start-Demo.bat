@echo off
setlocal
set "RTS_LAUNCHER=%ProgramFiles%\RTS Demo\windows\Launcher.ps1"
if not exist "%RTS_LAUNCHER%" set "RTS_LAUNCHER=%~dp0windows\stage\windows\Launcher.ps1"
if not exist "%RTS_LAUNCHER%" (
  echo Install dist\RTS-Demo-Setup-x64.exe first, then use the Desktop shortcut.
  pause
  exit /b 1
)
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%RTS_LAUNCHER%" -Action Start
