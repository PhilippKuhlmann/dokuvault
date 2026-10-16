#Requires -Modules ActiveDirectory
<#
  DokuVault - Offboarding __TITLE__

  Deaktiviert den AD-Benutzer, setzt ein neues Zufallskennwort und nimmt
  ihn aus den Gruppen der Checkliste. Geloescht wird nichts. Laesst sich
  gefahrlos mehrfach ausfuehren.

  Am Ende meldet das Skript das Ergebnis an DokuVault. Dort werden die
  erledigten Punkte abgehakt, der Benutzer als deaktiviert gefuehrt und das
  neue Kennwort verschluesselt abgelegt. Der Schluessel in diesem Skript gilt
  nur fuer diese eine Checkliste und nur bis __EXPIRES__.

  Auf einem Domaincontroller (oder Rechner mit RSAT-AD-Modul) ausfuehren:
    .\offboarding.ps1
#>
param(
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$Token = "__TOKEN__"

$Data = @'
__DATA__
'@ | ConvertFrom-Json

Import-Module ActiveDirectory

function New-RandomPassword {
    $sets = @('ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!$%&?#+-')
    $all = -join $sets
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $bytes = New-Object byte[] 24
    $rng.GetBytes($bytes)
    $chars = @()
    for ($i = 0; $i -lt 24; $i++) {
        $pool = if ($i -lt 4) { $sets[$i] } else { $all }
        $chars += $pool[$bytes[$i] % $pool.Length]
    }
    return -join ($chars | Sort-Object { Get-Random })
}

$done = @()
$errors = @()
$password = $null

$adUser = Get-ADUser -Identity $Data.user.username

if ($Data.disableItems.Count -gt 0) {
    Disable-ADAccount -Identity $adUser
    $done += $Data.disableItems
    Write-Host "Benutzer $($adUser.SamAccountName) deaktiviert." -ForegroundColor Green
}

if ($Data.resetItems.Count -gt 0) {
    $password = New-RandomPassword
    Set-ADAccountPassword -Identity $adUser -Reset -NewPassword (ConvertTo-SecureString $password -AsPlainText -Force)
    $done += $Data.resetItems
    Write-Host "Kennwort zurueckgesetzt." -ForegroundColor Green
}

# Already out of a group counts as done - a second run reports no errors.
$memberOf = @(Get-ADPrincipalGroupMembership -Identity $adUser | ForEach-Object { $_.Name })

foreach ($g in $Data.groups) {
    if ($memberOf -notcontains $g.name) {
        $done += $g.item
        Write-Host "Gruppe $($g.name): war schon entfernt." -ForegroundColor Yellow
        continue
    }
    try {
        Remove-ADGroupMember -Identity $g.name -Members $adUser -Confirm:$false
        $done += $g.item
        Write-Host "Gruppe $($g.name): entfernt." -ForegroundColor Green
    } catch {
        $errors += "Gruppe $($g.name): $($_.Exception.Message)"
        Write-Warning "Gruppe $($g.name): $($_.Exception.Message)"
    }
}

$enabled = (Get-ADUser -Identity $adUser).Enabled

$report = @{
    identifier = $adUser.ObjectGUID.Guid
    username   = $adUser.SamAccountName
    enabled    = [bool]$enabled
    password   = $password
    groups     = @($Data.groups | Where-Object { $done -contains $_.item } | ForEach-Object { $_.name })
    items      = @($done)
    errors     = @($errors)
} | ConvertTo-Json -Depth 4

try {
    Invoke-RestMethod -Method Post -Uri $ApiUrl -Headers @{ Authorization = "Bearer $Token" } `
        -ContentType "application/json; charset=utf-8" -Body ([Text.Encoding]::UTF8.GetBytes($report)) | Out-Null
    Write-Host "Ergebnis an DokuVault gemeldet." -ForegroundColor Green
} catch {
    Write-Warning "Meldung an DokuVault fehlgeschlagen: $($_.Exception.Message)"
}
