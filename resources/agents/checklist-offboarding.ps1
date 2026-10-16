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
  oder per Rechtsklick "Mit PowerShell ausfuehren". Ohne Administratorrechte
  startet es sich erhoeht neu. Das Fenster bleibt am Ende offen.
#>
param(
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"

function Wait-ForClose {
    Read-Host "`nEnter zum Schliessen" | Out-Null
}

# Right-click "Run with PowerShell" starts without admin rights - changing
# the user then fails with "access denied". Restart elevated (one UAC prompt).
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "Keine Administratorrechte - starte neu mit Administratorrechten ..." -ForegroundColor Yellow
    Start-Process powershell.exe -Verb RunAs -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`" -ApiUrl `"$ApiUrl`""
    Write-Host "Es geht im neuen Fenster weiter."
    Start-Sleep -Seconds 3
    exit
}

# Any uncaught error: show it and keep the window open.
trap {
    Write-Host "`nFEHLER: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Das Skript wurde abgebrochen. In DokuVault wurde nichts abgehakt." -ForegroundColor Red
    Wait-ForClose
    exit 1
}

function Write-Step([string]$Text) {
    Write-Host "`n$Text" -ForegroundColor Cyan
}

function Write-Ok([string]$Text) { Write-Host "  OK      $Text" -ForegroundColor Green }
function Write-Skip([string]$Text) { Write-Host "  SCHON   $Text" -ForegroundColor Yellow }
function Write-Fail([string]$Text) { Write-Host "  FEHLER  $Text" -ForegroundColor Red }

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

$adUser = Get-ADUser -Identity $Data.user.username -Properties DisplayName

Write-Host "DokuVault - Offboarding $(if ($adUser.DisplayName) { $adUser.DisplayName } else { $adUser.SamAccountName })" -ForegroundColor White
Write-Host "Benutzer:  $($adUser.SamAccountName)"
Write-Host "Domaene:   $((Get-ADDomain).DNSRoot)"

# --- 1. Deaktivieren ---------------------------------------------------------
Write-Step "[1/4] Benutzer deaktivieren"
if (@($Data.disableItems).Count -eq 0) {
    Write-Skip "Steht nicht (mehr) in der Checkliste."
} elseif (-not $adUser.Enabled) {
    Write-Skip "$($adUser.SamAccountName) ist schon deaktiviert."
    $done += $Data.disableItems
} else {
    Disable-ADAccount -Identity $adUser
    Write-Ok "$($adUser.SamAccountName) deaktiviert."
    $done += $Data.disableItems
}

# --- 2. Kennwort -------------------------------------------------------------
Write-Step "[2/4] Kennwort zuruecksetzen"
if (@($Data.resetItems).Count -eq 0) {
    Write-Skip "Steht nicht (mehr) in der Checkliste."
} else {
    $password = New-RandomPassword
    Set-ADAccountPassword -Identity $adUser -Reset -NewPassword (ConvertTo-SecureString $password -AsPlainText -Force)
    Write-Ok "Neues Zufallskennwort gesetzt."
    $done += $Data.resetItems
}

# --- 3. Gruppen --------------------------------------------------------------
# Already out of a group counts as done - a second run reports no errors.
Write-Step "[3/4] Aus Gruppen entfernen ($(@($Data.groups).Count))"
if (@($Data.groups).Count -eq 0) {
    Write-Skip "Keine Gruppen in dieser Checkliste."
}
$memberOf = @(Get-ADPrincipalGroupMembership -Identity $adUser | ForEach-Object { $_.Name })
foreach ($g in $Data.groups) {
    if ($memberOf -notcontains $g.name) {
        $done += $g.item
        Write-Skip "$($g.name) - war schon entfernt."
        continue
    }
    try {
        Remove-ADGroupMember -Identity $g.name -Members $adUser -Confirm:$false
        $done += $g.item
        Write-Ok "$($g.name)"
    } catch {
        $errors += "Gruppe $($g.name): $($_.Exception.Message)"
        Write-Fail "$($g.name): $($_.Exception.Message)"
    }
}

$enabled = (Get-ADUser -Identity $adUser).Enabled

# --- 4. Rueckmeldung ---------------------------------------------------------
Write-Step "[4/4] Ergebnis an DokuVault melden"
$report = @{
    identifier = $adUser.ObjectGUID.Guid
    username   = $adUser.SamAccountName
    enabled    = [bool]$enabled
    password   = $password
    groups     = @($Data.groups | Where-Object { $done -contains $_.item } | ForEach-Object { $_.name })
    items      = @($done)
    errors     = @($errors)
} | ConvertTo-Json -Depth 4

$reported = $false
try {
    Invoke-RestMethod -Method Post -Uri $ApiUrl -Headers @{ Authorization = "Bearer $Token" } `
        -ContentType "application/json; charset=utf-8" -Body ([Text.Encoding]::UTF8.GetBytes($report)) | Out-Null
    $reported = $true
    Write-Ok "Gemeldet. Die Punkte sind abgehakt, der Benutzer steht als deaktiviert in der Doku."
} catch {
    Write-Fail "Meldung fehlgeschlagen: $($_.Exception.Message)"
}

# --- Zusammenfassung ---------------------------------------------------------
Write-Host "`n----------------------------------------"
if ($errors.Count -eq 0) {
    Write-Host "Fertig ohne Fehler. $(@($done).Count) Punkte erledigt." -ForegroundColor Green
} else {
    Write-Host "Fertig mit $($errors.Count) Fehler(n). $(@($done).Count) Punkte erledigt:" -ForegroundColor Yellow
    $errors | ForEach-Object { Write-Host "  - $_" -ForegroundColor Yellow }
}
if (-not $reported -and $password) {
    Write-Host "`nNeues Kennwort bitte von Hand in DokuVault eintragen: $password" -ForegroundColor Yellow
}

Wait-ForClose
