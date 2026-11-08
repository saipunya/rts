param([ValidateSet('Start','Stop','Backup','Restore')][string]$Action = 'Start')
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName System.Security
$HomeDir = Split-Path -Parent $PSScriptRoot
$DataDir = Join-Path $env:LOCALAPPDATA 'RTSDemo'
$AppDir = Join-Path $DataDir 'app'
$LicenseDir = Join-Path $env:LOCALAPPDATA 'RTSDemoLicense'
$LicenseRegistry = 'HKCU:\Software\RTSDemo\Licensing'
$Php = Join-Path $HomeDir 'runtime\php\php.exe'
$MariaBin = Join-Path $HomeDir 'runtime\mariadb\bin'
$DbExe = Join-Path $MariaBin 'mariadbd.exe'
$StatePath = Join-Path $DataDir 'processes.json'
$Utf8 = New-Object System.Text.UTF8Encoding($false)
$DbPort = 33188
$WebPort = 8088
$env:RTS_DEMO_DATA = $DataDir
$env:RTS_DEMO_LICENSE_DIR = $LicenseDir
# Read the 64-bit Windows machine identity; do not expose the original MachineGuid.
$registryBase = [Microsoft.Win32.RegistryKey]::OpenBaseKey([Microsoft.Win32.RegistryHive]::LocalMachine, [Microsoft.Win32.RegistryView]::Registry64)
$machineKey = $registryBase.OpenSubKey('SOFTWARE\Microsoft\Cryptography')
try {
    $machineGuid = [string]$machineKey.GetValue('MachineGuid')
    if ([string]::IsNullOrWhiteSpace($machineGuid)) { throw 'Cannot read Windows installation identity.' }
    $sha = [Security.Cryptography.SHA256]::Create()
    try { $env:RTS_DEMO_MACHINE = ([BitConverter]::ToString($sha.ComputeHash($Utf8.GetBytes('RTS-DEMO|' + $machineGuid)))).Replace('-','').ToLowerInvariant() }
    finally { $sha.Dispose() }
} finally { if ($machineKey) { $machineKey.Dispose() }; $registryBase.Dispose() }
$env:RTS_DEMO_DB_PORT = [string]$DbPort
$script:State = @{ database = $null; web = $null }
function Write-Utf8($Path, $Value) { [IO.File]::WriteAllText($Path, $Value, $Utf8) }
function Notify($Text, $Kind = 'Information') {
    [void][Windows.Forms.MessageBox]::Show($Text, 'RTS Demo', 'OK', $Kind)
}
function Quote-Arg([string]$Text) {
    if ($Text.Contains('"') -or $Text.EndsWith('\')) { throw 'Unsupported argument path.' }
    return '"' + $Text + '"'
}
function Run-Tool($Exe, [string[]]$Arguments, [string]$InputText = '') {
    $info = New-Object Diagnostics.ProcessStartInfo
    $info.FileName = $Exe
    $info.Arguments = ($Arguments | ForEach-Object { Quote-Arg $_ }) -join ' '
    $info.UseShellExecute = $false; $info.CreateNoWindow = $true
    $info.RedirectStandardOutput = $true; $info.RedirectStandardError = $true
    $info.RedirectStandardInput = $true
    $info.StandardOutputEncoding = $Utf8; $info.StandardErrorEncoding = $Utf8
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $info
    [void]$process.Start()
    $outputTask = $process.StandardOutput.ReadToEndAsync()
    $errorTask = $process.StandardError.ReadToEndAsync()
    if ($InputText) {
        $inputBytes = $Utf8.GetBytes($InputText)
        $process.StandardInput.BaseStream.Write($inputBytes, 0, $inputBytes.Length)
    }
    $process.StandardInput.Close()
    $process.WaitForExit()
    $outputText = $outputTask.GetAwaiter().GetResult()
    $errorText = $errorTask.GetAwaiter().GetResult()
    if ($process.ExitCode -ne 0) { throw "Tool failed: $([IO.Path]::GetFileName($Exe))`r`n$outputText`r`n$errorText" }
    return $outputText
}
function Php-Args([string[]]$Arguments) {
    return @('-c', (Join-Path $DataDir 'php.ini')) + $Arguments
}
function Save-State { Write-Utf8 $StatePath ($script:State | ConvertTo-Json -Depth 4) }
function Find-OwnedProcess($Entry, $ExpectedPath) {
    if (-not $Entry) { return $null }
    $process = Get-Process -Id ([int]$Entry.id) -ErrorAction SilentlyContinue
    if (-not $process) { return $null }
    if ($process.Path -ne $ExpectedPath -or $process.StartTime.ToUniversalTime().Ticks.ToString() -ne [string]$Entry.ticks) {
        return $null # Stale PID; never stop a different process.
    }
    return $process
}
function Port-Open($Port) {
    $client = New-Object Net.Sockets.TcpClient
    try { $task = $client.ConnectAsync('127.0.0.1', $Port); [void]$task.Wait(250); return $client.Connected }
    catch { return $false }
    finally { $client.Dispose() }
}
function Start-Owned($Kind, $Exe, [string[]]$Arguments) {
    $outLog = Join-Path $DataDir ($Kind + '-stdout.log')
    $errLog = Join-Path $DataDir ($Kind + '-stderr.log')
    $quoted = ($Arguments | ForEach-Object { Quote-Arg $_ }) -join ' '
    $process = Start-Process -FilePath $Exe -ArgumentList $quoted -WorkingDirectory $DataDir -WindowStyle Hidden -RedirectStandardOutput $outLog -RedirectStandardError $errLog -PassThru
    $script:State[$Kind] = @{ id = $process.Id; ticks = $process.StartTime.ToUniversalTime().Ticks.ToString() }
    Save-State
    return $process
}
function New-Credentials {
    $form = New-Object Windows.Forms.Form
    $form.Text = 'RTS Demo - Create administrator'
    $form.Size = New-Object Drawing.Size(470,310)
    $form.StartPosition = 'CenterScreen'; $form.FormBorderStyle = 'FixedDialog'
    $form.MaximizeBox = $false; $form.MinimizeBox = $false
    $labels = @('สร้างบัญชีผู้ดูแลระบบสำหรับเครื่องนี้', 'ชื่อผู้ใช้', 'รหัสผ่าน (อย่างน้อย 10 ตัวอักษร)', 'ยืนยันรหัสผ่าน')
    $tops = @(16,52,107,162)
    for ($i=0; $i -lt $labels.Count; $i++) {
        $label = New-Object Windows.Forms.Label
        $label.Text = $labels[$i]; $label.Location = New-Object Drawing.Point(20,$tops[$i]); $label.AutoSize = $true
        $form.Controls.Add($label)
    }
    $nameBox = New-Object Windows.Forms.TextBox
    $nameBox.Location = New-Object Drawing.Point(20,75); $nameBox.Width = 410; $nameBox.Text = 'demo_admin'
    $passBox = New-Object Windows.Forms.TextBox
    $passBox.Location = New-Object Drawing.Point(20,130); $passBox.Width = 410; $passBox.UseSystemPasswordChar = $true
    $confirmBox = New-Object Windows.Forms.TextBox
    $confirmBox.Location = New-Object Drawing.Point(20,185); $confirmBox.Width = 410; $confirmBox.UseSystemPasswordChar = $true
    $button = New-Object Windows.Forms.Button
    $button.Text = 'สร้างบัญชี'; $button.Location = New-Object Drawing.Point(310,225); $button.Width = 120
    $button.Add_Click({
        if ($nameBox.Text -notmatch '^[A-Za-z0-9_\-]{1,100}$' -or $passBox.Text.Length -lt 10 -or $passBox.Text -ne $confirmBox.Text) {
            Notify 'ชื่อผู้ใช้ใช้ A-Z, a-z, 0-9, _ หรือ - และรหัสผ่านต้องตรงกัน ยาวอย่างน้อย 10 ตัวอักษร' 'Warning'; return
        }
        $form.DialogResult = 'OK'; $form.Close()
    })
    $form.Controls.AddRange(@($nameBox,$passBox,$confirmBox,$button)); $form.AcceptButton = $button
    if ($form.ShowDialog() -ne 'OK') { throw 'ยกเลิกการตั้งค่าครั้งแรก ข้อมูลเดิมไม่ได้ถูกลบ' }
    return @{ name = $nameBox.Text; password = $passBox.Text }
}
function Restore-LicenseCheckpoint {
    $property = Get-ItemProperty -LiteralPath $LicenseRegistry -Name 'Checkpoint' -ErrorAction SilentlyContinue
    if ($property -and $property.Checkpoint) {
        try {
            $bytes = [Convert]::FromBase64String($property.Checkpoint)
            $plain = [Security.Cryptography.ProtectedData]::Unprotect($bytes, $null, [Security.Cryptography.DataProtectionScope]::CurrentUser)
            $snapshot = $Utf8.GetString($plain)
            $null = Run-Tool $Php (Php-Args @((Join-Path $AppDir 'setup\license_state.php'), 'recover')) $snapshot
        } catch { throw 'Cannot recover the existing Windows license checkpoint. Contact Sumet: 080-8281906, LINE saipunya. Your data was preserved.' }
    }
}
function Save-LicenseCheckpoint {
    $tool = Join-Path $AppDir 'setup\license_state.php'
    if (-not (Test-Path $tool)) { return }
    $snapshot = Run-Tool $Php (Php-Args @($tool, 'export'))
    $bytes = $Utf8.GetBytes($snapshot)
    $protected = [Security.Cryptography.ProtectedData]::Protect($bytes, $null, [Security.Cryptography.DataProtectionScope]::CurrentUser)
    [void](New-Item -Path $LicenseRegistry -Force)
    Set-ItemProperty -LiteralPath $LicenseRegistry -Name 'Checkpoint' -Value ([Convert]::ToBase64String($protected))
}
function Stop-Web {
    $web = Find-OwnedProcess $script:State.web $Php
    if ($web) { Stop-Process -Id $web.Id; $web.WaitForExit(10000) | Out-Null }
    $script:State.web = $null; Save-State
}
function Ensure-Database {
    $dbProcess = Find-OwnedProcess $script:State.database $DbExe
    if (-not $dbProcess) {
        if (Port-Open $DbPort) { throw "Port $DbPort is used by another program. RTS did not change it. Close that program or contact support." }
        $dbProcess = Start-Owned 'database' $DbExe @(('--defaults-file=' + (Join-Path $DataDir 'my.ini')), '--console')
    }
    for ($i=0; $i -lt 150; $i++) {
        if ($dbProcess.HasExited) { throw "Database stopped. See $DataDir\database-stderr.log and mariadb.log" }
        try {
            $null = Run-Tool $Php (Php-Args @((Join-Path $AppDir 'setup\database.php'),'ping'))
            return
        } catch { Start-Sleep -Milliseconds 200 }
    }
    throw "Database did not become ready. See $DataDir\mariadb.log"
}
function Backup-Database {
    $directory = Join-Path $DataDir 'backups'
    [void][IO.Directory]::CreateDirectory($directory)
    $target = Join-Path $directory ('rts-demo-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff') + '.sql')
    $partial = $target + '.partial'
    $null = Run-Tool (Join-Path $MariaBin 'mariadb-dump.exe') @(('--defaults-extra-file=' + (Join-Path $DataDir 'client.ini')), '--single-transaction', '--routines', '--triggers', '--hex-blob', '--default-character-set=utf8mb4', ('--result-file=' + $partial), 'rts_demo')
    Write-Utf8 $target ("-- RTS-DEMO-BACKUP-v1`n" + [IO.File]::ReadAllText($partial, $Utf8))
    Remove-Item -LiteralPath $partial
    return $target
}
$mutex = $null; $locked = $false
try {
    $sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    $mutex = New-Object Threading.Mutex($false, ('Local\RTSDemo-' + $sid))
    try { $locked = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $locked = $true }
    if (-not $locked) { throw 'RTS is starting or backing up. Wait a moment and try again.' }
    [void][IO.Directory]::CreateDirectory($DataDir)
    if (Test-Path $StatePath) {
        $saved = Get-Content -Raw -LiteralPath $StatePath | ConvertFrom-Json
        $script:State.database = $saved.database; $script:State.web = $saved.web
    }
    if ($Action -eq 'Stop') {
        Stop-Web
        if (Test-Path (Join-Path $DataDir 'installed.txt')) {
            try { Save-LicenseCheckpoint } catch { Write-Warning 'License checkpoint could not be saved; continuing with safe database shutdown.' }
        }
        $owned = Find-OwnedProcess $script:State.database $DbExe
        if ($owned) {
            $null = Run-Tool (Join-Path $MariaBin 'mariadb-admin.exe') @(('--defaults-extra-file=' + (Join-Path $DataDir 'client.ini')), 'shutdown')
            if (-not $owned.WaitForExit(30000)) { throw 'Database is still stopping. Wait before shutting down Windows.' }
        }
        $script:State.database = $null; Save-State
        Notify 'ปิดระบบ RTS Demo เรียบร้อยแล้ว'; exit 0
    }
    foreach ($required in @($Php,$DbExe,(Join-Path $MariaBin 'mariadb-install-db.exe'))) {
        if (-not (Test-Path $required)) { throw 'Runtime missing. Reinstall RTS-Demo-Setup-x64.exe.' }
    }
    $version = (Get-Content -Raw (Join-Path $HomeDir 'version.txt')).Trim()
    $versionFile = Join-Path $DataDir 'app-version.txt'
    if (-not (Test-Path $versionFile) -or (Get-Content -Raw $versionFile).Trim() -ne $version) {
        if ((Find-OwnedProcess $script:State.web $Php) -or (Find-OwnedProcess $script:State.database $DbExe)) {
            throw 'Stop RTS Demo before updating application files.'
        }
        [void][IO.Directory]::CreateDirectory($AppDir)
        Copy-Item -Path (Join-Path $HomeDir 'app\*') -Destination $AppDir -Recurse -Force
        Write-Utf8 $versionFile $version
    }
    [void][IO.Directory]::CreateDirectory((Join-Path $DataDir 'tmp'))
    [void][IO.Directory]::CreateDirectory((Join-Path $DataDir 'sessions'))
    $phpDir = (Split-Path -Parent $Php).Replace('\','/')
    $dataUnix = $DataDir.Replace('\','/')
    Write-Utf8 (Join-Path $DataDir 'php.ini') @"
[PHP]
extension_dir="$phpDir/ext"
extension=mysqli
extension=mbstring
extension=gd
extension=openssl
extension=fileinfo
extension=curl
extension=zip
date.timezone=Asia/Bangkok
default_charset=UTF-8
memory_limit=512M
max_execution_time=120
upload_max_filesize=64M
post_max_size=64M
session.save_path="$dataUnix/sessions"
sys_temp_dir="$dataUnix/tmp"
upload_tmp_dir="$dataUnix/tmp"
log_errors=On
display_errors=Off
error_log="$dataUnix/php-error.log"
expose_php=Off
"@
    Restore-LicenseCheckpoint
    $config = Join-Path $DataDir 'db_config.php'
    if (-not (Test-Path $config)) {
        if ($Action -ne 'Start') { throw 'Start RTS Demo and complete first-time setup first.' }
        if (Test-Path (Join-Path $DataDir 'database')) { throw 'An unfinished database exists. Contact support; RTS will not erase it automatically.' }
        $credentials = New-Credentials
        $bytes = New-Object byte[] 24
        $rng = [Security.Cryptography.RandomNumberGenerator]::Create(); $rng.GetBytes($bytes); $rng.Dispose()
        $secret = ([BitConverter]::ToString($bytes)).Replace('-','').ToLowerInvariant()
        # The generated password contains only hex characters; it is not a command or log entry.
        Write-Utf8 $config "<?php`n`$db_host='127.0.0.1'; `$db_user='root'; `$db_pass='$secret'; `$db_name='rts_demo'; `$db_port=$DbPort;`n"
        Write-Utf8 (Join-Path $DataDir 'client.ini') "[client]`r`nhost=127.0.0.1`r`nport=$DbPort`r`nuser=root`r`npassword=$secret`r`nprotocol=tcp`r`n"
        $null = Run-Tool (Join-Path $MariaBin 'mariadb-install-db.exe') @(('--datadir=' + (Join-Path $DataDir 'database')), ('--password=' + $secret), ('--port=' + $DbPort))
        $baseUnix = (Split-Path -Parent $MariaBin).Replace('\','/')
        Write-Utf8 (Join-Path $DataDir 'my.ini') @"
[mysqld]
basedir="$baseUnix"
datadir="$dataUnix/database"
port=$DbPort
bind-address=127.0.0.1
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
log-error="$dataUnix/mariadb.log"
max_allowed_packet=64M
"@
        Ensure-Database
        $null = Run-Tool $Php (Php-Args @((Join-Path $AppDir 'setup\install.php'))) ($credentials.name + "`n" + $credentials.password + "`n")
        Write-Utf8 (Join-Path $DataDir 'installed.txt') $version
        $credentials = $null; $secret = $null
    }
    if (-not (Test-Path (Join-Path $DataDir 'installed.txt'))) { throw 'First-time setup did not finish. Contact support. Existing files were preserved for recovery.' }
    Ensure-Database
    $null = Run-Tool $Php (Php-Args @((Join-Path $AppDir 'setup\database.php'), 'check'))
    $null = Run-Tool $Php (Php-Args @((Join-Path $AppDir 'setup\license_state.php'), 'initialize'))
    Save-LicenseCheckpoint
    if ($Action -eq 'Backup') {
        $target = Backup-Database
        Notify "สำรองข้อมูลแล้ว:`r`n$target`r`nควรคัดลอกไฟล์นี้ไปเก็บนอกเครื่องด้วย"
        Start-Process explorer.exe -ArgumentList ('/select,' + (Quote-Arg $target)); exit 0
    }
    if ($Action -eq 'Restore') {
        $dialog = New-Object Windows.Forms.OpenFileDialog
        $dialog.Title = 'เลือกไฟล์สำรอง RTS Demo'; $dialog.Filter = 'RTS Demo backup (*.sql)|*.sql'
        if ($dialog.ShowDialog() -ne 'OK') { exit 0 }
        $confirm = [Windows.Forms.MessageBox]::Show('กู้คืนจะเปลี่ยนข้อมูลเดโมทั้งหมดเป็นข้อมูลในไฟล์นี้ ระบบจะสำรองข้อมูลปัจจุบันก่อน ต้องการดำเนินการหรือไม่?', 'RTS Demo', 'YesNo', 'Warning')
        if ($confirm -ne 'Yes') { exit 0 }
        Stop-Web
        $safety = Backup-Database
        # Validate the complete dump before sending any SQL to the database.
        $sql = [IO.File]::ReadAllText($dialog.FileName, $Utf8)
        if ($sql -notmatch '(?m)^-- RTS-DEMO-BACKUP-v1$' -or $sql -match '(?im)^\s*(CREATE\s+DATABASE|DROP\s+DATABASE|USE\s+|CREATE\s+USER|GRANT\s+)') {
            throw 'Unsupported backup. Choose an unmodified SQL file created by RTS Demo Backup.'
        }
        $null = Run-Tool (Join-Path $MariaBin 'mariadb.exe') @(('--defaults-extra-file=' + (Join-Path $DataDir 'client.ini')), '--default-character-set=utf8mb4','rts_demo') $sql
        Notify "กู้คืนสำเร็จ เปิด RTS Demo ใหม่ได้`r`nสำรองก่อนกู้คืน: $safety"; exit 0
    }
    $webProcess = Find-OwnedProcess $script:State.web $Php
    if (-not $webProcess) {
        if (Port-Open $WebPort) { throw "Port $WebPort is used by another program. RTS did not stop it. Close that program or contact support." }
        $tokenFile = Join-Path $DataDir 'health-token.txt'
        if (-not (Test-Path $tokenFile)) { Write-Utf8 $tokenFile ([Guid]::NewGuid().ToString('N')) }
        $env:RTS_DEMO_HEALTH = (Get-Content -Raw $tokenFile).Trim()
        $webProcess = Start-Owned 'web' $Php (Php-Args @('-S',"127.0.0.1:$WebPort",'-t',$AppDir,(Join-Path $AppDir 'router.php')))
    }
    $expectedToken = (Get-Content -Raw (Join-Path $DataDir 'health-token.txt')).Trim()
    $ready = $false
    for ($i=0; $i -lt 50; $i++) {
        if ($webProcess.HasExited) { throw "Web server stopped. See $DataDir\web-stderr.log" }
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$WebPort/_demo_health" -TimeoutSec 2
            if ($response.Content.Trim() -eq $expectedToken) { $ready = $true; break }
        } catch { Start-Sleep -Milliseconds 200 }
    }
    if (-not $ready) { throw 'Web server did not become ready. Check web-stderr.log.' }
    Start-Process "http://127.0.0.1:$WebPort/login.php"
} catch {
    $detail = $_.Exception.Message
    Notify ($detail + "`r`n`r`nข้อมูลและบันทึกระบบ: " + $DataDir) 'Error'
    exit 1
} finally {
    if ($locked -and $mutex) { $mutex.ReleaseMutex() }
    if ($mutex) { $mutex.Dispose() }
}
