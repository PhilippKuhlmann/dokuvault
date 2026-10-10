<#
  Auto-Dokumentation fuer Firewalls (OPNsense)  ->  DokuVault
  Auf einem Rechner ausfuehren, der die Firewall erreicht:
    .\firewall-doku.ps1 -Url "https://opnsense.local" -Key "..." -Secret "..."

  Key und Secret legt man in der OPNsense unter System -> Zugang -> Benutzer
  an (API-Schluessel). Ein eigener Benutzer genuegt; er braucht nur
  Leserechte auf die abgefragten Seiten (Dashboard, Schnittstellen, VPN,
  Firewall, Gateways).

  Meldet Version, Schnittstellen, VPNs (IPsec, OpenVPN, WireGuard),
  Portweiterleitungen und Gateways. Rein lesend - es wird nichts an der
  Firewall veraendert. Was eine aeltere Version nicht anbietet, fehlt einfach.

  Key und Secret werden hier beim Aufruf mitgegeben und NICHT in DokuVault
  gespeichert.
#>
param(
    [Parameter(Mandatory = $true)][string]$Url,
    [Parameter(Mandatory = $true)][string]$Key,
    [Parameter(Mandatory = $true)][string]$Secret,
    [ValidateSet("opnsense")][string]$Typ = "opnsense",
    [string]$ApiUrl = "__API_URL__",
    [switch]$ZertifikatIgnorieren
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"
$Url = $Url.TrimEnd('/')
if ($Url -notmatch '^https?://') { $Url = "https://$Url" }

<#
  Eine Firewall bringt ab Werk ein selbst signiertes Zertifikat mit.
  -ZertifikatIgnorieren schaltet die Pruefung ab; der Weg dorthin ist in
  PowerShell 5.1 (Callback) ein anderer als ab PowerShell 6 (Parameter).
#>
$RestExtra = @{}
if ($ZertifikatIgnorieren) {
    if ($PSVersionTable.PSVersion.Major -ge 6) {
        $RestExtra['SkipCertificateCheck'] = $true
    } else {
        Add-Type @"
using System.Net;
using System.Security.Cryptography.X509Certificates;
public class AlleZertifikate : ICertificatePolicy {
    public bool CheckValidationResult(ServicePoint sp, X509Certificate cert, WebRequest req, int problem) { return true; }
}
"@
        [System.Net.ServicePointManager]::CertificatePolicy = New-Object AlleZertifikate
    }
}
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

$Anmeldung = @{ Authorization = "Basic " + [Convert]::ToBase64String([System.Text.Encoding]::ASCII.GetBytes("${Key}:${Secret}")) }

<#
  Ein GET gegen die OPNsense-API. Liefert $null, wenn es den Endpunkt in
  dieser Version nicht gibt - die Doku soll an einer fehlenden Kleinigkeit
  nicht scheitern. Abgelehnte Schluessel und eine nicht erreichbare
  Firewall dagegen brechen ab: dann stimmt etwas Grundsaetzliches nicht.
#>
function Get-Api([string]$Pfad) {
    try {
        return Invoke-RestMethod -Method Get -Uri "$Url/api/$Pfad" -Headers $Anmeldung @RestExtra
    } catch {
        $code = $null
        if ($_.Exception.Response) { $code = [int]$_.Exception.Response.StatusCode }
        if ($code -eq 401) {
            Write-Host "Fehler: die Firewall lehnt Key und Secret ab (HTTP 401)." -ForegroundColor Red
            exit 1
        }
        if (-not $code) {
            Write-Host "Fehler: $Url nicht erreichbar: $($_.Exception.Message)" -ForegroundColor Red
            Write-Host "  Adresse pruefen. Selbstsigniertes Zertifikat: -ZertifikatIgnorieren" -ForegroundColor Red
            exit 1
        }
        return $null
    }
}

# Der erste gefuellte Wert - PowerShell 5.1 kennt kein "??".
function Get-Erster {
    foreach ($wert in $args) {
        if ($null -ne $wert -and "$wert" -ne "") { return "$wert" }
    }
    return $null
}

function Get-Zeilen($Antwort) {
    if ($Antwort -and $Antwort.rows) { return @($Antwort.rows) }
    return @()
}

Write-Host "Frage $Url ab ..."

$firmware = Get-Api "core/firmware/info"
$status = Get-Api "core/firmware/status"
$system = Get-Api "diagnostics/system/system_information"
$schnittstellen = Get-Zeilen (Get-Api "interfaces/overview/interfaces_info")

$hostname = ([uri]$Url).Host

# Die MAC der ersten Schnittstelle bleibt, auch wenn jemand Namen oder
# Adressen aendert - darueber findet der naechste Lauf denselben Eintrag.
$mac = $schnittstellen | ForEach-Object { $_.macaddr } |
    Where-Object { $_ -and $_ -ne '00:00:00:00:00:00' } | Select-Object -First 1

$interfaces = @($schnittstellen | Where-Object { $_.enabled -ne $false } | ForEach-Object {
    $ip = Get-Erster $_.addr4 $(if ($_.ipv4) { @($_.ipv4)[0].ipaddr })
    $vlan = $null
    if ("$($_.vlan_tag)" -match '^\d+$') { $vlan = [int]$_.vlan_tag }
    [PSCustomObject]@{
        name   = Get-Erster $_.description $_.identifier $_.device "?"
        device = Get-Erster $_.device
        ip     = $ip
        vlan   = $vlan
    }
})

$vpns = @()
foreach ($z in Get-Zeilen (Get-Api "ipsec/connections/search_connection")) {
    $vpns += [PSCustomObject]@{ type = "IPsec"; name = Get-Erster $z.description "IPsec"; remote = Get-Erster $z.remote_addrs }
}
foreach ($z in Get-Zeilen (Get-Api "ipsec/tunnel/search_phase1")) {
    $vpns += [PSCustomObject]@{ type = "IPsec"; name = Get-Erster $z.description $z.remote_gateway "IPsec"; remote = Get-Erster $z.remote_gateway }
}
foreach ($z in Get-Zeilen (Get-Api "openvpn/instances/search")) {
    $vpns += [PSCustomObject]@{ type = "OpenVPN"; name = Get-Erster $z.description $z.role "OpenVPN"; remote = Get-Erster $z.remote }
}
foreach ($z in Get-Zeilen (Get-Api "wireguard/client/search_client")) {
    $vpns += [PSCustomObject]@{ type = "WireGuard"; name = Get-Erster $z.name "WireGuard"; remote = Get-Erster $z.serveraddress }
}

$portForwards = @(Get-Zeilen (Get-Api "firewall/d_nat/search_rule") | Where-Object { "$($_.disabled)" -ne "1" } | ForEach-Object {
    [PSCustomObject]@{
        description = Get-Erster $_.descr $_.description
        interface   = Get-Erster $_.interface
        protocol    = Get-Erster $_.protocol
        port        = Get-Erster $_.'destination.port' $_.destination_port $_.dstport
        target      = Get-Erster $_.target
        target_port = Get-Erster $_.local_port $_.'local-port'
    }
})

$gatewayAntwort = Get-Api "routes/gateway/status"
$gateways = @()
if ($gatewayAntwort -and $gatewayAntwort.items) {
    $gateways = @($gatewayAntwort.items | ForEach-Object {
        [PSCustomObject]@{ name = Get-Erster $_.name "?"; address = Get-Erster $_.address }
    })
}

$version = $null
$produkt = $null
if ($firmware) {
    if ($firmware.product) {
        $version = Get-Erster $firmware.product.product_version
        $produkt = Get-Erster $firmware.product.product_name
    }
    $version = Get-Erster $version $firmware.product_version
    $produkt = Get-Erster $produkt $firmware.product_name
}
if ($status) { $version = Get-Erster $version $status.product_version }

$name = $hostname
if ($system) { $name = Get-Erster $system.name $hostname }

$payload = [PSCustomObject]@{
    identifier     = "opnsense:" + (Get-Erster $mac $hostname)
    name           = $name
    manufacturer   = "OPNsense"
    model          = $produkt
    firmware       = $version
    management_url = $Url
    interfaces     = $interfaces
    vpns           = $vpns
    port_forwards  = $portForwards
    gateways       = $gateways
} | ConvertTo-Json -Depth 5

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. $($interfaces.Count) Schnittstellen, $($vpns.Count) VPNs, $($portForwards.Count) Portweiterleitungen gemeldet."
