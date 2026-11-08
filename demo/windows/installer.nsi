Unicode true
!include "MUI2.nsh"
!include "x64.nsh"
!include "WinVer.nsh"
Name "RTS Demo"
OutFile "../dist/RTS-Demo-Setup-x64.exe"
InstallDir "$PROGRAMFILES64\RTS Demo"
RequestExecutionLevel admin
SetCompressor /FINAL zlib
ShowInstDetails show
ShowUninstDetails show
!define MUI_ABORTWARNING
!define MUI_FINISHPAGE_TEXT "Installation complete. Open RTS Demo from the Desktop to create your administrator account. The trial lasts 60 days. Contact the developer for an activation code to continue. Read the Thai user guide before first use. Your data is stored separately for each Windows user."
!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro MUI_LANGUAGE "English"
!insertmacro MUI_LANGUAGE "Thai"
Function .onInit
  ${IfNot} ${RunningX64}
    MessageBox MB_ICONSTOP "RTS Demo requires 64-bit Windows."
    Abort
  ${EndIf}
  ${IfNot} ${AtLeastWin10}
    MessageBox MB_ICONSTOP "RTS Demo requires Windows 10 or later."
    Abort
  ${EndIf}
  SetRegView 64
FunctionEnd
Section "RTS Demo (required)" Main
  SectionIn RO
  InitPluginsDir
  SetOutPath "$PLUGINSDIR"
  File "Check-Install.ps1"
  ExecWait '"$SYSDIR\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "$PLUGINSDIR\Check-Install.ps1" -InstallRoot "$INSTDIR"' $0
  ${If} $0 != 0
    MessageBox MB_ICONSTOP "Please stop RTS Demo for every Windows user before installing or updating. No existing data was removed."
    Abort
  ${EndIf}
  SetOutPath "$INSTDIR"
  File /r "stage/*"
  ExecWait '"$SYSDIR\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "$INSTDIR\windows\Verify-Microsoft.ps1" -File "$INSTDIR\runtime\vc_redist.x64.exe"' $0
  ${If} $0 != 0
    MessageBox MB_ICONSTOP "Microsoft runtime signature validation failed. Installation cannot continue."
    Abort
  ${EndIf}
  ExecWait '"$INSTDIR\runtime\vc_redist.x64.exe" /install /quiet /norestart' $0
  ${If} $0 == 3010
    SetRebootFlag true
  ${ElseIf} $0 != 0
  ${AndIf} $0 != 1638
    MessageBox MB_ICONSTOP "Microsoft runtime installation failed (code $0)."
    Abort
  ${EndIf}
  nsExec::ExecToStack '"$INSTDIR\runtime\php\php.exe" -n -v'
  Pop $0
  Pop $1
  ${If} $0 != 0
    MessageBox MB_ICONSTOP "PHP could not start. Restart Windows and run this installer again."
    Abort
  ${EndIf}
  WriteUninstaller "$INSTDIR\Uninstall.exe"
  SetShellVarContext all
  CreateDirectory "$SMPROGRAMS\RTS Demo"
  CreateShortcut "$DESKTOP\RTS Demo.lnk" "$WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$INSTDIR\windows\Launcher.ps1" -Action Start' "$INSTDIR\app\assets\rts-demo.ico"
  CreateShortcut "$SMPROGRAMS\RTS Demo\Start RTS Demo.lnk" "$WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$INSTDIR\windows\Launcher.ps1" -Action Start' "$INSTDIR\app\assets\rts-demo.ico"
  CreateShortcut "$SMPROGRAMS\RTS Demo\Stop RTS Demo.lnk" "$WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$INSTDIR\windows\Launcher.ps1" -Action Stop'
  CreateShortcut "$SMPROGRAMS\RTS Demo\Backup RTS Demo.lnk" "$WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$INSTDIR\windows\Launcher.ps1" -Action Backup'
  CreateShortcut "$SMPROGRAMS\RTS Demo\Restore RTS Demo.lnk" "$WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$INSTDIR\windows\Launcher.ps1" -Action Restore'
  CreateShortcut "$SMPROGRAMS\RTS Demo\User Guide.lnk" "$INSTDIR\manual\คู่มือใช้งาน.html"
  CreateShortcut "$SMPROGRAMS\RTS Demo\Uninstall.lnk" "$INSTDIR\Uninstall.exe"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RTSDemo" "DisplayName" "RTS Demo"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RTSDemo" "DisplayVersion" "0.2.0"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RTSDemo" "UninstallString" '"$INSTDIR\Uninstall.exe"'
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RTSDemo" "InstallLocation" "$INSTDIR"
  WriteRegDWORD HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RTSDemo" "NoModify" 1
  WriteRegDWORD HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RTSDemo" "NoRepair" 1
SectionEnd
Section "Uninstall"
  SetRegView 64
  ExecWait '"$SYSDIR\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "$INSTDIR\windows\Check-Install.ps1" -InstallRoot "$INSTDIR"' $0
  ${If} $0 != 0
    MessageBox MB_ICONSTOP "Stop RTS Demo for every Windows user before uninstalling."
    Abort
  ${EndIf}
  SetShellVarContext all
  Delete "$DESKTOP\RTS Demo.lnk"
  RMDir /r "$SMPROGRAMS\RTS Demo"
  ; Only remove known installed program directories. User databases live elsewhere.
  RMDir /r "$INSTDIR\runtime"
  RMDir /r "$INSTDIR\app"
  RMDir /r "$INSTDIR\windows"
  RMDir /r "$INSTDIR\manual"
  RMDir /r "$INSTDIR\licenses"
  Delete "$INSTDIR\version.txt"
  Delete "$INSTDIR\runtime-lock.json"
  Delete "$INSTDIR\Uninstall.exe"
  RMDir "$INSTDIR"
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RTSDemo"
  MessageBox MB_ICONINFORMATION "Program removed. Your data and backups remain in %LOCALAPPDATA%\RTSDemo for each Windows user."
SectionEnd
