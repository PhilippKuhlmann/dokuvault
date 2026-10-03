<#
  Auto-Dokumentation fuer Veeam Backup & Replication  ->  DokuVault
  Meldet die Backup-Jobs (Quelle, Ziel-Repository, Zeitplan, Aufbewahrung)
  und das Ergebnis des letzten Laufs. Nur lesend.
  Auf dem Veeam-Server ausfuehren:
    .\veeam-doku.ps1

  Ziel-URL ueberschreiben, ohne einen neuen Token zu erzeugen:
    .\veeam-doku.ps1 -ApiUrl "https://doku.example/api/agent/backup"
#>
param(
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"

# Veeam 11 and later ship a module, older versions a snap-in.
if (Get-Module -ListAvailable -Name Veeam.Backup.PowerShell) {
    Import-Module Veeam.Backup.PowerShell -WarningAction SilentlyContinue
} else {
    Add-PSSnapin VeeamPSSnapIn
}

function Get-Zeitplan($Job) {
    try {
        if (-not $Job.IsScheduleEnabled) { return "manuell" }
        $o = $Job.ScheduleOptions
        if ($o.OptionsDaily.Enabled) {
            $zeit = ([datetime]$o.OptionsDaily.TimeLocal).ToString("HH:mm")
            if ("$($o.OptionsDaily.Kind)" -eq "Everyday") { return "taeglich $zeit" }
            if ("$($o.OptionsDaily.Kind)" -eq "WeekDays") { return "werktags $zeit" }
            return "$($o.OptionsDaily.Days -join ', ') $zeit"
        }
        if ($o.OptionsMonthly.Enabled) {
            return "monatlich " + ([datetime]$o.OptionsMonthly.TimeLocal).ToString("HH:mm")
        }
        if ($o.OptionsPeriodically.Enabled) {
            return "alle $($o.OptionsPeriodically.FullPeriod) Minuten"
        }
        if ($o.OptionsContinuous.Enabled) { return "durchgehend" }
        if ($o.OptionsScheduleAfterJob.IsEnabled) { return "nach einem anderen Job" }
    } catch { }
    return $null
}

function Get-Aufbewahrung($Job) {
    try {
        $opt = $Job.GetOptions().BackupStorageOptions
        if ("$($opt.RetentionType)" -eq "Days") { return "$($opt.RetainDaysToKeep) Tage" }
        return "$($opt.RetainCycles) Wiederherstellungspunkte"
    } catch { return $null }
}

function Get-Status($Result) {
    switch ("$Result") {
        "Success" { return "ok" }
        "Warning" { return "warning" }
        "Failed"  { return "failed" }
        default   { return $null }
    }
}

# All sessions once - per job "the last successful one" without asking
# Veeam again for every job.
$sitzungen = @(Get-VBRBackupSession)

$jobs = @()
foreach ($job in Get-VBRJob) {
    $quelle = $null
    try { $quelle = (@($job.GetObjectsInJob()) | ForEach-Object { $_.Name }) -join ", " } catch { }
    $ziel = $null
    try { $ziel = $job.GetTargetRepository().Name } catch { }

    $letzte = $null
    try { $letzte = $job.FindLastSession() } catch { }
    $erfolg = $sitzungen | Where-Object { $_.JobId -eq $job.Id -and "$($_.Result)" -eq "Success" } |
        Sort-Object EndTime -Descending | Select-Object -First 1

    $jobs += [PSCustomObject]@{
        identifier   = "veeam/$($job.Id)"
        name         = $job.Name
        software     = "Veeam Backup & Replication"
        source       = $quelle
        destination  = $ziel
        schedule     = Get-Zeitplan $job
        retention    = Get-Aufbewahrung $job
        last_status  = if ($letzte) { Get-Status $letzte.Result } else { $null }
        last_run_at  = if ($letzte -and $letzte.EndTime -gt [datetime]"2000-01-01") { $letzte.EndTime.ToString("o") } else { $null }
        last_success = if ($erfolg) { $erfolg.EndTime.ToString("o") } else { $null }
    }
}

$payload = [PSCustomObject]@{ jobs = $jobs } | ConvertTo-Json -Depth 4

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload)) | Out-Null

Write-Host "Fertig. $($jobs.Count) Veeam-Jobs gemeldet."
