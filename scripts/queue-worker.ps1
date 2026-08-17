$ErrorActionPreference = 'Continue'

$projectPath = Split-Path -Parent $PSScriptRoot
$artisanPath = Join-Path $projectPath 'artisan'
$logPath = Join-Path $projectPath 'storage\logs\queue-worker.log'
$phpPath = (Get-Command php -ErrorAction Stop).Source

Set-Location -LiteralPath $projectPath

while ($true) {
    & $phpPath $artisanPath queue:work database --queue=default --sleep=3 --tries=3 --timeout=90 --max-time=3600 *>> $logPath
    Start-Sleep -Seconds 5
}
