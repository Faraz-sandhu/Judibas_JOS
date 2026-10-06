$ErrorActionPreference='Stop'
$judibasRoot=Split-Path -Parent $PSScriptRoot
$judibasBin=Join-Path $judibasRoot '.local\postgresql17\pgsql\bin'
$judibasData=Join-Path $judibasRoot '.local\pgdata'
& (Join-Path $judibasBin 'pg_ctl.exe') -D $judibasData status
if ($LASTEXITCODE -eq 0) { exit 0 }
& (Join-Path $judibasBin 'pg_ctl.exe') -D $judibasData -l (Join-Path $judibasRoot '.local\postgresql.log') -w start
exit $LASTEXITCODE
