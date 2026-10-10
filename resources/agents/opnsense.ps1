<#
  Auto-Dokumentation fuer Firewalls: OPNsense  ->  DokuVault
  Auf einem Rechner ausfuehren, der die Firewall erreicht:
    .\opnsense-doku.ps1 -Url "https://opnsense.local" -Key "..." -Secret "..."

  Key und Secret legt man in der OPNsense unter System -> Zugang -> Benutzer
  an (API-Schluessel). Ein eigener Benutzer mit diesen Rechten genuegt:
    Lobby: Dashboard, System: Firmware, Status: Interfaces,
    Interfaces: VLAN, VPN: IPsec: Connections, VPN: OpenVPN: Instances,
    VPN: WireGuard: Configuration, Firewall: NAT: Destination NAT,
    System: Gateways
    und fuer DHCP: Services: Dnsmasq DNS/DHCP: Settings oder
    Services: DHCP: Kea(v4) - je nachdem, welcher laeuft
  Reine Leserechte kennt die OPNsense nicht - das Script liest nur, der
  Schluessel koennte mehr. Ihn deshalb nicht weitergeben.

  Meldet Version, Schnittstellen und ihre Netze samt DHCP-Bereich, VPNs
  (IPsec, OpenVPN, WireGuard), Portweiterleitungen und Gateways. Rein
  lesend - es wird nichts an der Firewall veraendert. Was eine aeltere Version nicht anbietet, fehlt einfach.

  Key und Secret werden hier beim Aufruf mitgegeben und NICHT in DokuVault
  gespeichert.

  Nur fuer OPNsense. Securepoint hat ein eigenes Script; beide melden an
  denselben Endpunkt.
#>
param(
    [Parameter(Mandatory = $true)][string]$Url,
    [Parameter(Mandatory = $true)][string]$Key,
    [Parameter(Mandatory = $true)][string]$Secret,
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
function Get-Api([string]$Pfad, [switch]$Optional) {
    try {
        return Invoke-RestMethod -Method Get -Uri "$Url/api/$Pfad" -Headers $Anmeldung @RestExtra
    } catch {
        $code = $null
        if ($_.Exception.Response) { $code = [int]$_.Exception.Response.StatusCode }
        if ($code -eq 401) {
            Write-Host "Fehler: die Firewall lehnt Key und Secret ab (HTTP 401)." -ForegroundColor Red
            exit 1
        }
        # Key gilt, aber dem Benutzer fehlt das Recht auf diese Seite. Nicht
        # stillschweigend uebergehen - sonst kommt eine fast leere Firewall an.
        if ($code -eq 403) {
            if ($Optional) { $script:OhneRechtOptional += $Pfad } else { $script:OhneRecht += $Pfad }
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
$script:OhneRecht = @()
$script:OhneRechtOptional = @()

$firmware = Get-Api "core/firmware/info"
$status = Get-Api "core/firmware/status"
$system = Get-Api "diagnostics/system/system_information"
# Nur zugewiesene Schnittstellen: Loopback und die unbelegten (enc0,
# pflog0) haben keine Kennung und gehoeren nicht in die Doku.
# VPN-Tunnel (WireGuard, OpenVPN, IPsec) koennen zugewiesen sein, sind aber
# keine Netze der Doku - die VPNs stehen unten eigens.
$schnittstellen = @(Get-Zeilen (Get-Api "interfaces/overview/interfaces_info") |
    Where-Object { $_.identifier -and $_.device -ne 'lo0' -and "$($_.device)" -notmatch '^(wg|ovpn|tun|tap|ipsec|enc|gif|gre|ppp)' })

$hostname = ([uri]$Url).Host

# interfaces_info nennt den VLAN-Tag nicht immer - die VLAN-Liste kennt ihn
# zum Geraetenamen (vlan01).
$vlanTags = @{}
foreach ($v in Get-Zeilen (Get-Api "interfaces/vlan_settings/search_item")) {
    if ($v.vlanif) { $vlanTags[$v.vlanif] = $v.tag }
}

# Abgeschaltete VPNs sind keine Verbindung, die jemand nachschlagen muss.
function Test-Aktiv($Zeile) { return "$($Zeile.enabled)" -ne "0" }

# Die MAC der LAN-Schnittstelle (sonst der ersten) bleibt, auch wenn jemand
# Namen oder Adressen aendert - darueber findet der naechste Lauf denselben
# Eintrag.
$mac = @($schnittstellen | Where-Object { $_.identifier -eq 'lan' }) + @($schnittstellen) |
    ForEach-Object { $_.macaddr } |
    Where-Object { $_ -and $_ -ne '00:00:00:00:00:00' } | Select-Object -First 1

$interfaces = @($schnittstellen | Where-Object { $_.enabled -ne $false } | ForEach-Object {
    $ip = Get-Erster $_.addr4 $(if ($_.ipv4) { @($_.ipv4)[0].ipaddr })
    $vlan = $null
    $tag = Get-Erster $_.vlan_tag $vlanTags["$($_.device)"]
    if ("$tag" -match '^\d+$') { $vlan = [int]$tag }
    [PSCustomObject]@{
        name   = Get-Erster $_.description $_.identifier $_.device "?"
        device = Get-Erster $_.device
        # WAN wird kein VLAN in der Doku - das Netz gehoert dem Provider.
        wan    = ("$($_.identifier)" -match '^wan')
        ip     = $ip
        vlan   = $vlan
    }
})

$vpns = @()
foreach ($z in Get-Zeilen (Get-Api "ipsec/connections/search_connection") | Where-Object { Test-Aktiv $_ }) {
    $vpns += [PSCustomObject]@{ type = "IPsec"; name = Get-Erster $z.description "IPsec"; remote = Get-Erster $z.remote_addrs }
}
foreach ($z in Get-Zeilen (Get-Api "ipsec/tunnel/search_phase1" -Optional) | Where-Object { Test-Aktiv $_ }) {
    $vpns += [PSCustomObject]@{ type = "IPsec"; name = Get-Erster $z.description $z.remote_gateway "IPsec"; remote = Get-Erster $z.remote_gateway }
}
foreach ($z in Get-Zeilen (Get-Api "openvpn/instances/search") | Where-Object { Test-Aktiv $_ }) {
    # Ein OpenVPN-Server hat keine Gegenstelle - dort sagen Tunnelnetz und
    # Port, was er ist.
    $gegenstelle = Get-Erster $z.remote
    if ($z.role -eq 'server') {
        $port = ((@((Get-Erster $z.'%proto' $z.proto), (Get-Erster $z.port)) | Where-Object { $_ }) -join ' ')
        $gegenstelle = Get-Erster ((@((Get-Erster $z.server), (Get-Erster $port)) | Where-Object { $_ }) -join ' · ')
    }
    $vpns += [PSCustomObject]@{ type = "OpenVPN"; name = Get-Erster $z.description $z.role "OpenVPN"; remote = $gegenstelle }
}
foreach ($z in Get-Zeilen (Get-Api "wireguard/client/search_client") | Where-Object { Test-Aktiv $_ }) {
    $vpns += [PSCustomObject]@{ type = "WireGuard"; name = Get-Erster $z.name "WireGuard"; remote = Get-Erster $z.serveraddress }
}

# Die Anti-Lockout-Regeln legt die OPNsense selbst an; sie leiten nichts
# weiter (nordr) und sind keine Doku wert.
$portForwards = @(Get-Zeilen (Get-Api "firewall/d_nat/search_rule") |
    Where-Object { "$($_.disabled)" -ne "1" -and $_.is_automatic -ne $true -and "$($_.nordr)" -ne "1" } | ForEach-Object {
    [PSCustomObject]@{
        description = Get-Erster $_.descr $_.description
        interface   = Get-Erster $_.'%interface' $_.interface
        protocol    = Get-Erster $_.'%protocol' $_.protocol
        port        = Get-Erster $_.'destination.port' $_.destination_port $_.dstport
        target      = Get-Erster $_.target
        target_port = Get-Erster $_.local_port $_.'local-port'
    }
})

# DHCP laeuft ueber Dnsmasq (Standard seit 25.1) oder Kea - meist nur einer,
# und nur fuer den braucht der Benutzer das Recht. Welches Netz zu einem
# Bereich gehoert, rechnet DokuVault aus.
# "8.8.8.8, 1.1.1.1" -> 8.8.8.8, 1.1.1.1; nur IPv4.
function Get-Adressen($Wert) {
    return @("$Wert" -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -match '^\d+(\.\d+){3}$' })
}

$dhcpRanges = @()
$dnsmasq = Get-Api "dnsmasq/settings/search_range" -Optional
# DHCP-Option 6 (DNS-Server) - je Schnittstelle oder fuer alle.
$dnsOptionen = @(Get-Zeilen (Get-Api "dnsmasq/settings/search_option" -Optional) |
    Where-Object { "$($_.option)" -eq '6' -and "$($_.type)" -ne 'match' })
$kea = Get-Api "kea/dhcpv4/search_subnet" -Optional
foreach ($z in Get-Zeilen $dnsmasq) {
    if ("$($z.start_addr)" -match '^\d+\.' -and $z.end_addr) {
        # Ohne eigene Option verteilt die OPNsense sich selbst - dann bleibt
        # dns leer und DokuVault traegt die Firewall ein.
        $option = @($dnsOptionen | Where-Object { $_.interface -eq $z.interface }) +
            @($dnsOptionen | Where-Object { -not $_.interface }) | Select-Object -First 1
        $dhcpRanges += [PSCustomObject]@{ start = $z.start_addr; end = $z.end_addr; dns = (Get-Adressen $option.value) }
    }
}
foreach ($z in Get-Zeilen $kea) {
    $dns = Get-Adressen (Get-Erster $z.'option_data.domain_name_servers' $(if ($z.option_data) { $z.option_data.domain_name_servers }))
    foreach ($pool in ("$($z.pools)" -split '[\r\n, ]+')) {
        if ($pool -match '^([\d.]+)-([\d.]+)$') {
            $dhcpRanges += [PSCustomObject]@{ start = $Matches[1]; end = $Matches[2]; dns = $dns }
        }
    }
}
if ($script:OhneRechtOptional -contains "dnsmasq/settings/search_range" -and $script:OhneRechtOptional -contains "kea/dhcpv4/search_subnet") {
    $script:OhneRecht += "dnsmasq/settings/search_range oder kea/dhcpv4/search_subnet (DHCP-Bereiche)"
}

$gatewayAntwort = Get-Api "routes/gateway/status"
$gateways = @()
if ($gatewayAntwort -and $gatewayAntwort.items) {
    $gateways = @($gatewayAntwort.items | ForEach-Object {
        # "~" schreibt die OPNsense, wo ein Gateway keine Adresse hat.
        $adresse = $_.address
        if ($adresse -eq '~') { $adresse = $null }
        [PSCustomObject]@{ name = Get-Erster $_.name "?"; address = Get-Erster $adresse }
    })
}

$version = $null
if ($firmware) {
    if ($firmware.product) { $version = Get-Erster $firmware.product.product_version }
    $version = Get-Erster $version $firmware.product_version
}
if ($status) {
    if ($status.product) { $version = Get-Erster $version $status.product.product_version }
    $version = Get-Erster $version $status.product_version
}

$name = $hostname
if ($system) { $name = Get-Erster $system.name $hostname }

if ($script:OhneRecht.Count -gt 0) {
    Write-Host "Hinweis: dem API-Benutzer fehlen Rechte (HTTP 403) fuer:" -ForegroundColor Yellow
    $script:OhneRecht | ForEach-Object { Write-Host "  /api/$_" -ForegroundColor Yellow }
    Write-Host "  In der OPNsense unter System -> Zugang -> Benutzer -> Rechte ergaenzen." -ForegroundColor Yellow
    # Gar nichts lesbar: nichts melden, statt eine leere Firewall anzulegen.
    if ($script:OhneRecht -contains "interfaces/overview/interfaces_info") {
        Write-Host "Fehler: ohne Zugriff auf die Schnittstellen wird nichts gemeldet." -ForegroundColor Red
        exit 1
    }
}

$payload = [PSCustomObject]@{
    identifier     = "opnsense:" + (Get-Erster $mac $hostname)
    name           = $name
    manufacturer   = "OPNsense"
    # Das Produkt heisst immer "OPNsense" - als Modell sagte das nichts.
    model          = $null
    firmware       = $version
    management_url = $Url
    interfaces     = $interfaces
    vpns           = $vpns
    port_forwards  = $portForwards
    gateways       = $gateways
    dhcp_ranges    = $dhcpRanges
} | ConvertTo-Json -Depth 5

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. $($interfaces.Count) Schnittstellen, $($vpns.Count) VPNs, $($portForwards.Count) Portweiterleitungen gemeldet."
