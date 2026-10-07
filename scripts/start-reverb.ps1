$ErrorActionPreference='Stop'
$judibasRoot=Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $judibasRoot
$judibasPhp=Get-Command php -ErrorAction SilentlyContinue
if ($judibasPhp) { & $judibasPhp.Source artisan reverb:start }
else { & 'E:\laragon\bin\php\php-8.2.33-nts-Win32-vs16-x64\php.exe' artisan reverb:start }
