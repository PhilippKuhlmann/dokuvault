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

  Auf einem Domaincontroller (oder Rechner mit RSAT-AD-Modul) ausfuehren:
    .\onboarding.ps1
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

# Start-Kennwort: 16 Zeichen aus einem kryptografischen Zufallsgenerator,
# je Zeichenklasse mindestens eines - erfuellt die Standard-Komplexitaet.
function New-StartPassword {
    $sets = @('ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!$%&?#+-')
    $all = -join $sets
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $bytes = New-Object byte[] 16
    $rng.GetBytes($bytes)
    $chars = @()
    for ($i = 0; $i -lt 16; $i++) {
        $pool = if ($i -lt 4) { $sets[$i] } else { $all }
        $chars += $pool[$bytes[$i] % $pool.Length]
    }
    # Die festen Klassen nicht immer vorne lassen.
    return -join ($chars | Sort-Object { Get-Random })
}

$u = $Data.user
$done = @()
$errors = @()
$password = $null

$existing = Get-ADUser -Filter "SamAccountName -eq '$($u.username)'" -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Benutzer $($u.username) gibt es schon - wird nicht neu angelegt." -ForegroundColor Yellow
} else {
    $password = New-StartPassword
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
    Write-Host "Benutzer $($u.username) angelegt." -ForegroundColor Green
    $done += $Data.createItems
}

$adUser = Get-ADUser -Identity $u.username -Properties EmailAddress, proxyAddresses

# The address also under proxyAddresses (Exchange, Entra Connect): primary
# (SMTP:) unless the user already has one, then as an alias (smtp:).
if ($u.email) {
    $addresses = @($adUser.proxyAddresses)
    if (-not ($addresses | Where-Object { $_ -ieq "smtp:$($u.email)" })) {
        $prefix = if ($addresses | Where-Object { $_ -cmatch '^SMTP:' }) { 'smtp:' } else { 'SMTP:' }
        try {
            Set-ADUser -Identity $adUser -Add @{ proxyAddresses = "$prefix$($u.email)" }
            Write-Host "proxyAddresses: $prefix$($u.email) eingetragen." -ForegroundColor Green
        } catch {
            $errors += "proxyAddresses: $($_.Exception.Message)"
            Write-Warning "proxyAddresses: $($_.Exception.Message)"
        }
    }
}

foreach ($g in $Data.groups) {
    try {
        Add-ADGroupMember -Identity $g.name -Members $adUser
        $done += $g.item
        Write-Host "Gruppe $($g.name): aufgenommen." -ForegroundColor Green
    } catch {
        $errors += "Gruppe $($g.name): $($_.Exception.Message)"
        Write-Warning "Gruppe $($g.name): $($_.Exception.Message)"
    }
}

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

try {
    Invoke-RestMethod -Method Post -Uri $ApiUrl -Headers @{ Authorization = "Bearer $Token" } `
        -ContentType "application/json; charset=utf-8" -Body ([Text.Encoding]::UTF8.GetBytes($report)) | Out-Null
    Write-Host "Ergebnis an DokuVault gemeldet. Das Start-Kennwort steht dort beim AD-Benutzer." -ForegroundColor Green
} catch {
    Write-Warning "Meldung an DokuVault fehlgeschlagen: $($_.Exception.Message)"
    if ($password) {
        Write-Host "Start-Kennwort bitte von Hand dokumentieren: $password" -ForegroundColor Yellow
    }
}
