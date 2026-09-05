<#
  Auto-Dokumentation fuer Microsoft 365 / Entra ID  ->  DokuVault
  Auf einem Rechner mit Internetzugang ausfuehren:
    .\microsoft365-doku.ps1 -TenantId "..." -ClientId "..." -ClientSecret "..."

  Meldet Postfaecher, verifizierte Domains und gebuchte Lizenzen. Rein lesend -
  im Tenant wird nichts veraendert.

  Voraussetzung ist eine einmalig angelegte App-Registrierung im Entra-Portal
  mit ausschliesslich lesenden Anwendungsberechtigungen:
    User.Read.All, Domain.Read.All, Organization.Read.All
  (jeweils "Application permission", danach einmal Administratorzustimmung).

  Tenant-Id, Client-Id und Secret werden hier beim Aufruf mitgegeben und NICHT
  in DokuVault gespeichert. Kennwoerter von Benutzern gibt Graph ohnehin nicht
  heraus und dieses Script fragt auch nicht danach.
#>
param(
    [Parameter(Mandatory = $true)][string]$TenantId,
    [Parameter(Mandatory = $true)][string]$ClientId,
    [Parameter(Mandatory = $true)][string]$ClientSecret,
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"

# PowerShell 5.1 spricht ab Werk noch TLS 1.0 - damit weist Microsoft die
# Anmeldung ab.
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

$antwort = Invoke-RestMethod -Method Post -Uri "https://login.microsoftonline.com/$TenantId/oauth2/v2.0/token" `
    -ContentType "application/x-www-form-urlencoded" `
    -Body @{
        client_id     = $ClientId
        client_secret = $ClientSecret
        scope         = "https://graph.microsoft.com/.default"
        grant_type    = "client_credentials"
    }

$kopf = @{ Authorization = "Bearer $($antwort.access_token)" }

<#
  Graph liefert hoechstens 100 Objekte je Seite und verweist mit
  @odata.nextLink auf die naechste. Ohne das Nachladen waere ab dem 101.
  Postfach die Doku still unvollstaendig - der schlimmste Fehler, den eine
  Dokumentation machen kann.
#>
function Get-GraphAlle([string]$url) {
    $alle = @()
    while ($url) {
        $seite = Invoke-RestMethod -Method Get -Uri $url -Headers $kopf
        $alle += $seite.value
        $url = $seite.'@odata.nextLink'
    }
    return $alle
}

# Nur Konten mit Postfach: Raeume, Dienstkonten ohne Mail und
# Gastbenutzer ohne Adresse gehoeren nicht in die Postfachliste.
$benutzer = Get-GraphAlle 'https://graph.microsoft.com/v1.0/users?$select=id,displayName,mail,userPrincipalName,accountEnabled&$top=100'
$mailboxes = @($benutzer | Where-Object { $_.mail } | ForEach-Object {
    [PSCustomObject]@{
        identifier = $_.id
        name       = $_.displayName
        mail       = $_.mail
        username   = $_.userPrincipalName
    }
})

$domains = @(Get-GraphAlle 'https://graph.microsoft.com/v1.0/domains' | Where-Object { $_.isVerified } | ForEach-Object {
    [PSCustomObject]@{
        identifier = $_.id
        name       = $_.id
    }
})

<#
  skuPartNumber ist eine Kennung wie O365_BUSINESS_PREMIUM. Graph kennt keinen
  Anzeigenamen dazu - aus den Unterstrichen werden Leerzeichen, mehr laesst
  sich ehrlich nicht daraus machen.
#>
$lizenzen = @(Get-GraphAlle 'https://graph.microsoft.com/v1.0/subscribedSkus' | ForEach-Object {
    [PSCustomObject]@{
        identifier = $_.skuId
        name       = ($_.skuPartNumber -replace '_', ' ')
        gebucht    = [int]$_.prepaidUnits.enabled
        belegt     = [int]$_.consumedUnits
    }
})

$payload = [PSCustomObject]@{
    tenant    = $TenantId
    mailboxes = $mailboxes
    domains   = $domains
    licences  = $lizenzen
} | ConvertTo-Json -Depth 5

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. $($mailboxes.Count) Postfaecher, $($domains.Count) Domains, $($lizenzen.Count) Lizenzen gemeldet."
