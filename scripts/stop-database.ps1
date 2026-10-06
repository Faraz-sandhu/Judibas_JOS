$judibasRoot=Split-Path -Parent $PSScriptRoot
& (Join-Path $judibasRoot '.local\postgresql17\pgsql\bin\pg_ctl.exe') -D (Join-Path $judibasRoot '.local\pgdata') -m fast -w stop
exit $LASTEXITCODE
