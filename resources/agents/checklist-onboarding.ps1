#Requires -Modules ActiveDirectory
<#
  DokuVault - Onboarding __TITLE__

  Legt den AD-Benutzer im Standard-Container an, traegt die E-Mail-Adresse
  auch unter proxyAddresses ein und nimmt ihn in die Gruppen der Checkliste
  auf. Laesst sich gefahrlos mehrfach ausfuehren:
  Ein vorhandener Benutzer wird nicht neu angelegt, eine vorhandene
  Mitgliedschaft nicht doppelt.

  Am Ende meldet das Skript das Ergebnis an DokuVault. Dort werden die
  erledigten Punkte abgehakt, und das Start-Kennwort wird verschluesselt
  beim AD-Benutzer abgelegt. Der Schluessel in diesem Skript gilt nur fuer
  diese eine Checkliste und nur bis __EXPIRES__.

  Die heruntergeladene .cmd auf einem Domaincontroller (oder Rechner mit
  RSAT-AD-Modul) doppelklicken. Ohne Administratorrechte startet sie sich
  erhoeht neu. Das Fenster bleibt am Ende offen.
#>
param(
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"

function Wait-ForClose {
    Read-Host "`nEnter zum Schliessen" | Out-Null
}

# The .cmd wrapper restarts itself elevated. Started any other way without
# admin rights, stop with a hint instead of failing halfway.
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "Keine Administratorrechte - bitte als Administrator ausfuehren." -ForegroundColor Red
    Wait-ForClose
    exit 1
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

# Start password as set for this customer in DokuVault: either a fixed one,
# or built from the rule - the upper case letters, special characters and
# digits, the rest lower case letters (no l/I/O/0/1).
function New-StartPassword($rule) {
    if ($rule.mode -eq 'fixed') { return $rule.fixed }

    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    function Pick([string]$set) {
        $b = New-Object byte[] 4
        $rng.GetBytes($b)
        return $set[[BitConverter]::ToUInt32($b, 0) % $set.Length]
    }

    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ'
    $lower = 'abcdefghijkmnopqrstuvwxyz'
    $digits = '23456789'
    $specials = if ($rule.specials) { [string]$rule.specials } else { '!' }

    $chars = @()
    for ($i = 0; $i -lt [int]$rule.uppercase; $i++) { $chars += Pick $upper }
    for ($i = 0; $i -lt [int]$rule.specialCount; $i++) { $chars += Pick $specials }
    for ($i = 0; $i -lt [int]$rule.digits; $i++) { $chars += Pick $digits }
    while ($chars.Count -lt [int]$rule.length) { $chars += Pick $lower }

    return -join ($chars | Sort-Object { Get-Random })
}

$u = $Data.user
$done = @()
$errors = @()
$password = $null

Write-Host "DokuVault - Onboarding $($u.displayName)" -ForegroundColor White
Write-Host "Benutzer:  $($u.username)"
Write-Host "Anmeldung: $($u.upn)"
if ($u.email) { Write-Host "E-Mail:    $($u.email)" }
Write-Host "Domaene:   $((Get-ADDomain).DNSRoot)"

# --- 1. Benutzer -------------------------------------------------------------
Write-Step "[1/4] Benutzer anlegen"
$existing = Get-ADUser -Filter "SamAccountName -eq '$($u.username)'" -ErrorAction SilentlyContinue
if ($existing) {
    Write-Skip "$($u.username) gibt es schon - wird nicht neu angelegt."
} else {
    $password = New-StartPassword $Data.password
    $params = @{
        Name                  = $u.displayName
        DisplayName           = $u.displayName
        GivenName             = $u.firstName
        Surname               = $u.lastName
        SamAccountName        = $u.username
        UserPrincipalName     = $u.upn
        AccountPassword       = (ConvertTo-SecureString $password -AsPlainText -Force)
        Enabled               = $true
        ChangePasswordAtLogon = [bool]$u.changePasswordAtLogon
    }
    if ($u.email) { $params.EmailAddress = $u.email }
    New-ADUser @params
    Write-Ok "$($u.username) angelegt, aktiv, mit Start-Kennwort$(if ($Data.password.mode -eq 'fixed') { ' (festes Kennwort des Kunden)' })."
    if ($u.changePasswordAtLogon) {
        Write-Ok "Kennwort muss bei der ersten Anmeldung geaendert werden."
    }
    $done += $Data.createItems
}

$adUser = Get-ADUser -Identity $u.username -Properties EmailAddress, proxyAddresses

# --- 2. proxyAddresses -------------------------------------------------------
# The address also under proxyAddresses (Exchange, Entra Connect): primary
# (SMTP:) unless the user already has one, then as an alias (smtp:).
Write-Step "[2/4] E-Mail-Adresse unter proxyAddresses"
if (-not $u.email) {
    Write-Skip "Keine E-Mail-Adresse angegeben - nichts zu tun."
} else {
    $addresses = @($adUser.proxyAddresses)
    if ($addresses | Where-Object { $_ -ieq "smtp:$($u.email)" }) {
        Write-Skip "$($u.email) steht schon drin."
    } else {
        $prefix = if ($addresses | Where-Object { $_ -cmatch '^SMTP:' }) { 'smtp:' } else { 'SMTP:' }
        try {
            Set-ADUser -Identity $adUser -Add @{ proxyAddresses = "$prefix$($u.email)" }
            Write-Ok "$prefix$($u.email) eingetragen."
        } catch {
            $errors += "proxyAddresses: $($_.Exception.Message)"
            Write-Fail "proxyAddresses: $($_.Exception.Message)"
        }
    }
}

# --- 3. Gruppen --------------------------------------------------------------
Write-Step "[3/4] Gruppen ($(@($Data.groups).Count))"
if (@($Data.groups).Count -eq 0) {
    Write-Skip "Keine Gruppen in dieser Checkliste."
}
foreach ($g in $Data.groups) {
    try {
        Add-ADGroupMember -Identity $g.name -Members $adUser
        $done += $g.item
        Write-Ok "$($g.name)"
    } catch {
        $errors += "Gruppe $($g.name): $($_.Exception.Message)"
        Write-Fail "$($g.name): $($_.Exception.Message)"
    }
}

# --- 4. Rueckmeldung ---------------------------------------------------------
Write-Step "[4/4] Ergebnis an DokuVault melden"
$report = @{
    identifier = $adUser.ObjectGUID.Guid
    username   = $adUser.SamAccountName
    firstName  = $adUser.GivenName
    lastName   = $adUser.Surname
    email      = $adUser.EmailAddress
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
    Write-Ok "Gemeldet. Die Punkte sind abgehakt, das Start-Kennwort steht beim AD-Benutzer."
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
    Write-Host "`nStart-Kennwort bitte von Hand in DokuVault eintragen: $password" -ForegroundColor Yellow
}

Wait-ForClose
