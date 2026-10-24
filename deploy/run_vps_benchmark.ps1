# Run the benchmark re-run (E-4) from the Windows client against the VPS database server.
#
#   powershell -ExecutionPolicy Bypass -File deploy\run_vps_benchmark.ps1 -ServerIp <IP> [-Key <key.pem>] [-SshUser root]
#
# Steps: connection test -> 100-query benchmark (+ sweeps) -> diversity benchmark -> compare with
# the published data -> copy server_spec.txt from the server. Results go to data\rerun_<date>.
# The DB password is asked once (hidden), kept only in this PowerShell process, and removed at the
# end. .env is not read for the connection and not modified: real environment variables win over
# .env (config/database.php).
param(
    [Parameter(Mandatory = $true)][string]$ServerIp,
    [string]$Key = "",          # .pem key; leave empty for providers that use an SSH password
    [string]$Out = ("data\rerun_" + (Get-Date -Format "yyyyMMdd")),
    [string]$SshUser = "ubuntu",
    [int]$Port = 3306,
    [int]$SshPort = 22
)
$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

if ($ServerIp -notmatch '^\d{1,3}(\.\d{1,3}){3}$') { throw "ServerIp must be an IPv4 address" }
if ($Key -and -not (Test-Path $Key)) { throw "Key file not found: $Key" }
if (Test-Path $Out) { throw "$Out already exists. Choose another -Out so earlier results are not overwritten." }
foreach ($c in "php", "python", "scp") {
    if (-not (Get-Command $c -ErrorAction SilentlyContinue)) { throw "$c not found in PATH" }
}

$sec = $null
if (-not $env:DB_PASS) {   # a DB_PASS already in the environment (automated test) is used as is
    $sec = Read-Host "Password of MySQL user 'fkse' (the one typed on the server)" -AsSecureString
    $env:DB_PASS = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
}
$env:DB_HOST = $ServerIp; $env:DB_PORT = "$Port"; $env:DB_USER = "fkse"; $env:DB_NAME = "master_icd_x"

function Step($name, [scriptblock]$cmd) {
    Write-Host ""
    Write-Host "== $name" -ForegroundColor Cyan
    # Windows PowerShell 5.1 turns any stderr line of a native program into an error record when
    # combined with 2>&1; with "Stop" that would abort a run that is actually fine. Judge by exit code.
    $ErrorActionPreference = "Continue"
    & $cmd
    if ($LASTEXITCODE -ne 0) { throw "$name failed (exit $LASTEXITCODE)" }
}

$log = $null
# Windows PowerShell 5.1 Tee-Object writes UTF-16; write the log as UTF-8 instead.
function Log { process { $_; "$_" | Out-File -FilePath $log -Append -Encoding utf8 } }
try {
    New-Item -ItemType Directory -Path $Out | Out-Null
    $log = Join-Path $Out "run_log.txt"
    "client: $env:COMPUTERNAME  $((Get-CimInstance Win32_Processor).Name)  RAM $([math]::Round((Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory/1GB,1)) GB" | Log
    "php: $(php -r 'echo PHP_VERSION;')   server: $ServerIp   started: $(Get-Date -Format s)" | Log

    Step "1/5 Connection test" { php tests\test_db_connection.php 2>&1 | Log }
    Step "2/5 Main benchmark: 100 queries + k/tau/theta sweeps (several minutes)" {
        php scripts\run_benchmark.php --queries=data\benchmark_100_queries.json --out=$Out 2>&1 | Log }
    Step "3/5 Diversity benchmark (Appendix A)" {
        php scripts\run_diversity_benchmark.php --queries=data\diversity_benchmark.json --out=$Out 2>&1 | Log }
    Step "4/5 Compare with published data (only latency may differ)" {
        python scripts\compare_rerun.py $Out 2>&1 | Log }
    Step "5/5 Copy server_spec.txt from the server" {
        $keyArg = @(); if ($Key) { $keyArg = @("-i", $Key) }
        scp @keyArg -P $SshPort "${SshUser}@${ServerIp}:~/server_spec.txt" (Join-Path $Out "server_spec.txt") }
    "finished: $(Get-Date -Format s)" | Log
    Write-Host ""
    Write-Host "DONE. Results in $Out. You can now Terminate the instance in the AWS console." -ForegroundColor Green
}
finally {
    Remove-Item Env:DB_PASS -ErrorAction SilentlyContinue
    if ($sec) { $sec.Dispose() }
}
