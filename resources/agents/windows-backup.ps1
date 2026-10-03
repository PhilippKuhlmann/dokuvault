<#
  Auto-Dokumentation fuer die Windows Server-Sicherung  ->  DokuVault
  Meldet die eingerichtete Sicherung (was, wohin, wann) und das Ergebnis der
  letzten Sicherung. Nur lesend.
  Auf dem Windows-Server ausfuehren:
    .\windows-backup-doku.ps1

  Ziel-URL ueberschreiben, ohne einen neuen Token zu erzeugen:
    .\windows-backup-doku.ps1 -ApiUrl "https://doku.example/api/agent/backup"
#>
param(
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"

Import-Module WindowsServerBackup

$policy = Get-WBPolicy
$jobs = @()

if ($policy) {
    $quelle = @()
    try { $quelle += @(Get-WBVolume -Policy $policy | ForEach-Object { $_.MountPath }) } catch { }
    try { if (Get-WBSystemState -Policy $policy) { $quelle += "Systemstatus" } } catch { }
    try { if (Get-WBBareMetalRecovery -Policy $policy) { $quelle += "Bare-Metal-Recovery" } } catch { }
    try { $quelle += @(Get-WBFileSpec -Policy $policy | ForEach-Object { $_.FileSpec }) } catch { }

    $ziel = $null
    try {
        $ziel = (@(Get-WBBackupTarget -Policy $policy) | ForEach-Object {
            if ($_.Label) { $_.Label } elseif ($_.Path) { $_.Path } else { "$($_.TargetType)" }
        }) -join ", "
    } catch { }

    $zeitplan = $null
    try { $zeitplan = "taeglich " + ((@(Get-WBSchedule -Policy $policy) | ForEach-Object { $_.ToString("HH:mm") }) -join ", ") } catch { }

    $summary = Get-WBSummary
    $status = $null
    $letzter = $null
    if ($summary.LastBackupTime -and $summary.LastBackupTime -gt [datetime]"2000-01-01") {
        $letzter = $summary.LastBackupTime.ToString("o")
        $status = if ($summary.LastBackupResultHR -eq 0) { "ok" } else { "failed" }
    }
    $erfolg = $null
    if ($summary.LastSuccessfulBackupTime -and $summary.LastSuccessfulBackupTime -gt [datetime]"2000-01-01") {
        $erfolg = $summary.LastSuccessfulBackupTime.ToString("o")
    }

    # The last 20 backups - the history on the backup overview in DokuVault.
    $laeufe = @()
    try {
        $laeufe = @(Get-WBJob -Previous 20 | Where-Object { "$($_.JobType)" -eq "Backup" -and $_.EndTime -gt [datetime]"2000-01-01" } |
            ForEach-Object {
                [PSCustomObject]@{
                    status      = if ($_.HResult -eq 0) { "ok" } else { "failed" }
                    finished_at = $_.EndTime.ToString("o")
                }
            })
    } catch { }

    $jobs += [PSCustomObject]@{
        identifier   = "wsb/$((Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Cryptography').MachineGuid)"
        name         = "Windows Server-Sicherung $env:COMPUTERNAME"
        software     = "Windows Server-Sicherung"
        source       = ($quelle -join ", ")
        destination  = $ziel
        schedule     = $zeitplan
        last_status  = $status
        last_run_at  = $letzter
        last_success = $erfolg
        runs         = $laeufe
    }
}

$payload = [PSCustomObject]@{ jobs = $jobs } | ConvertTo-Json -Depth 6

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload)) | Out-Null

if ($policy) {
    Write-Host "Fertig. Sicherung von $env:COMPUTERNAME gemeldet."
} else {
    Write-Host "Keine Sicherung eingerichtet (Get-WBPolicy leer) - nichts gemeldet."
}
