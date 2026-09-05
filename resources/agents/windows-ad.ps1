#Requires -Modules ActiveDirectory
<#
  Auto-Dokumentation fuer Windows Active Directory  ->  DokuVault
  Auf einem Domaincontroller (oder Rechner mit RSAT-AD-Modul) ausfuehren:
    .\windows-ad-doku.ps1

  Ziel-URL ueberschreiben, ohne einen neuen Token zu erzeugen:
    .\windows-ad-doku.ps1 -ApiUrl "https://doku.example/api/agent/windows-ad"
#>
param(
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"

Import-Module ActiveDirectory

function Get-Rid([string]$SidValue) {
    $parts = $SidValue -split '-'
    return [int]$parts[$parts.Length - 1]
}

# --- Benutzer sammeln ---
# Eingebauter Administrator (RID 500) bleibt drin, uebrige System-Konten
# (Gast/501, krbtgt/502, DefaultAccount/503, ...) werden ausgeschlossen.
$users = @()
Get-ADUser -Filter * -Properties GivenName, Surname, SamAccountName, EmailAddress, Enabled, ObjectGUID, SID |
    ForEach-Object {
        $rid = Get-Rid $_.SID.Value
        if ($rid -ge 1000 -or $rid -eq 500) {
            $users += [PSCustomObject]@{
                identifier = $_.ObjectGUID.Guid
                firstName  = $_.GivenName
                lastName   = $_.Surname
                username   = $_.SamAccountName
                email      = $_.EmailAddress
                enabled    = [bool]$_.Enabled
            }
        }
    }

# --- Gruppen sammeln ---
# Nur selbst angelegte Gruppen: keine Built-in-Gruppen (isCriticalSystemObject)
# und keine System-RIDs (< 1000).
$groups = @()
Get-ADGroup -Filter * -Properties Description, isCriticalSystemObject, SID, ObjectGUID |
    ForEach-Object {
        $rid = Get-Rid $_.SID.Value
        if (-not $_.isCriticalSystemObject -and $rid -ge 1000) {
            $groups += [PSCustomObject]@{
                identifier  = $_.ObjectGUID.Guid
                name        = $_.Name
                description = $_.Description
            }
        }
    }

$domain = (Get-ADDomain).DNSRoot

$payload = [PSCustomObject]@{
    domain = $domain
    users  = $users
    groups = $groups
} | ConvertTo-Json -Depth 5

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. $($users.Count) Benutzer, $($groups.Count) Gruppen gemeldet."
