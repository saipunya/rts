param([Parameter(Mandatory=$true)][string]$File)
$ErrorActionPreference = 'Stop'
try {
    $signature = Get-AuthenticodeSignature -LiteralPath $File
    if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'O=Microsoft Corporation') { exit 2 }
    exit 0
} catch { exit 3 }
