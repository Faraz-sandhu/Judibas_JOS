$ErrorActionPreference='Stop'
$judibasRoot=Split-Path -Parent $PSScriptRoot
$judibasBin=Join-Path $judibasRoot '.local\postgresql17\pgsql\bin'
$judibasData=Join-Path $judibasRoot '.local\pgdata'
& (Join-Path $judibasBin 'pg_ctl.exe') -D $judibasData status
if ($LASTEXITCODE -eq 0) { exit 0 }
# Use a fresh log for each start: Windows may retain a handle to an older log.
$judibasLogDirectory=Join-Path $judibasRoot '.local\postgresql-logs'
New-Item -ItemType Directory -Path $judibasLogDirectory -Force | Out-Null
$judibasLog=Join-Path $judibasLogDirectory (('postgresql-{0}-{1}.log' -f (Get-Date -Format 'yyyyMMdd-HHmmss'),[guid]::NewGuid().ToString('N')))
Write-Host "PostgreSQL startup log: $judibasLog"
& (Join-Path $judibasBin 'pg_ctl.exe') -D $judibasData -l $judibasLog -w start
exit $LASTEXITCODE
