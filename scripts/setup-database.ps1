$ErrorActionPreference = 'Stop'
$judibasRoot = Split-Path -Parent $PSScriptRoot
$judibasLocal = Join-Path $judibasRoot '.local'
$judibasBin = Join-Path $judibasLocal 'postgresql17\pgsql\bin'
$judibasData = Join-Path $judibasLocal 'pgdata'
if (!(Test-Path (Join-Path $judibasBin 'initdb.exe'))) { throw 'PostgreSQL binaries are not available yet.' }
if (Test-Path (Join-Path $judibasData 'PG_VERSION')) { throw 'Database already initialized; use start-database.ps1.' }
function New-JudibasPassword {
$judibasBytes = New-Object byte[] 32
$judibasRng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$judibasRng.GetBytes($judibasBytes)
$judibasRng.Dispose()
return [BitConverter]::ToString($judibasBytes).Replace('-','').ToLowerInvariant()
}
$judibasAdminPassword = New-JudibasPassword
$judibasAppPassword = New-JudibasPassword
$judibasPwFile = Join-Path $judibasLocal 'init-password'
[System.IO.File]::WriteAllText($judibasPwFile, $judibasAdminPassword, (New-Object System.Text.UTF8Encoding($false)))
try {
& (Join-Path $judibasBin 'initdb.exe') -D $judibasData -U postgres --encoding=UTF8 --locale=C --auth=scram-sha-256 --pwfile=$judibasPwFile
if ($LASTEXITCODE -ne 0) { throw 'Database initialization failed.' }
} finally { Remove-Item -LiteralPath $judibasPwFile }
Add-Content -LiteralPath (Join-Path $judibasData 'postgresql.conf') -Value "`nlisten_addresses = '127.0.0.1'`nport = 5432`npassword_encryption = 'scram-sha-256'`n"
[System.IO.File]::WriteAllText((Join-Path $judibasLocal 'postgres-admin.json'), (ConvertTo-Json @{username='postgres';password=$judibasAdminPassword}), (New-Object System.Text.UTF8Encoding($false)))
& (Join-Path $judibasBin 'pg_ctl.exe') -D $judibasData -l (Join-Path $judibasLocal 'postgresql.log') -w start
if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL did not start.' }
$judibasOldPgPassword = $env:PGPASSWORD
try {
$env:PGPASSWORD = $judibasAdminPassword
$judibasSql = "CREATE ROLE judibas_app LOGIN PASSWORD '$judibasAppPassword'; CREATE DATABASE judibas OWNER judibas_app;"
$judibasSql | & (Join-Path $judibasBin 'psql.exe') -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1
if ($LASTEXITCODE -ne 0) { throw 'Creating the application database failed.' }
} finally { $env:PGPASSWORD = $judibasOldPgPassword }
$judibasEnvPath = Join-Path $judibasRoot '.env'
$judibasEnvContent = [System.IO.File]::ReadAllText($judibasEnvPath)
$judibasEnvContent = $judibasEnvContent -replace '(?m)^DB_CONNECTION=.*$', 'DB_CONNECTION=pgsql' -replace '(?m)^DB_HOST=.*$', 'DB_HOST=127.0.0.1' -replace '(?m)^DB_PORT=.*$', 'DB_PORT=5432' -replace '(?m)^DB_DATABASE=.*$', 'DB_DATABASE=judibas' -replace '(?m)^DB_USERNAME=.*$', 'DB_USERNAME=judibas_app' -replace '(?m)^DB_PASSWORD=.*$', "DB_PASSWORD=$judibasAppPassword"
[System.IO.File]::WriteAllText($judibasEnvPath, $judibasEnvContent, (New-Object System.Text.UTF8Encoding($false)))
Write-Output 'PostgreSQL is running on 127.0.0.1:5432. Judibas credentials saved to .env.'
