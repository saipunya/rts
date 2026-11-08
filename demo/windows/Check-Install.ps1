param([Parameter(Mandatory=$true)][string]$InstallRoot)
$ErrorActionPreference = 'Stop'
try {
    $prefix = [IO.Path]::GetFullPath($InstallRoot).TrimEnd('\') + '\runtime\'
    $active = Get-CimInstance Win32_Process | Where-Object { $_.ExecutablePath -and $_.ExecutablePath.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase) }
    if ($active) { exit 2 }
    exit 0
} catch { exit 3 }
