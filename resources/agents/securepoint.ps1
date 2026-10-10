<#
  Auto-Dokumentation fuer Firewalls: Securepoint UTM  ->  DokuVault
  Auf einem Rechner ausfuehren, der die UTM per SSH erreicht:
    .\securepoint-doku.ps1 -Host "192.168.175.1"

  Die UTM hat keine REST-API. Das Script meldet sich deshalb per SSH an
  (OpenSSH-Client, ab Windows 10 dabei) und liest alles mit "spcli --json" -
  in EINER Verbindung, das Kennwort wird also nur einmal abgefragt (oder ein
  SSH-Schluessel genommen, -Schluessel). Gebraucht wird ein Konto, das spcli
  aufrufen darf (root).

  Meldet Version, Seriennummer, Lizenzlaufzeit, Schnittstellen und ihre
  Netze samt DHCP-Bereich, VPNs (IPsec, OpenVPN, WireGuard),
  Portweiterleitungen und Gateways. Rein lesend - es wird nichts an der UTM
  veraendert.

  Pre-Shared Keys und andere Geheimnisse liefert spcli mit aus. Das Script
  nimmt davon nichts mit: gesendet werden nur Name und Gegenstelle eines VPN.
  Das SSH-Kennwort bleibt hier und wird NICHT in DokuVault gespeichert.

  Nur fuer Securepoint. OPNsense hat ein eigenes Script; beide melden an
  denselben Endpunkt.
#>
param(
    # $Host ist in PowerShell belegt - der Parameter heisst deshalb Utm,
    # -Host funktioniert als zweiter Name trotzdem.
    [Parameter(Mandatory = $true)][Alias('Host')][string]$Utm,
    [string]$User = "root",
    [int]$Port = 22,
    [string]$Schluessel = "",
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"

if (-not (Get-Command ssh -ErrorAction SilentlyContinue)) {
    Write-Host "Fehler: kein ssh gefunden. Unter Windows: Einstellungen -> Apps -> Optionale Features -> OpenSSH-Client." -ForegroundColor Red
    exit 1
}

<#
  Alles in einer Sitzung, jede Antwort hinter einer eigenen Marke. Eine
  Zeile ohne doppelte Anfuehrungszeichen: PowerShell 5.1 reicht die an
  ssh.exe nicht zuverlaessig weiter. Die DHCP-Pools stehen nicht in spcli,
  sondern in der erzeugten Konfiguration: ab UTM 14 Dnsmasq, davor ISC dhcpd.
#>
$befehle = "'system info' 'interface get' 'interface address get' 'interface zone get' 'ipsec get' 'openvpn get' 'wireguard get' 'rule get' 'node get' 'service get' 'route get'"
$fern = "for c in $befehle; do echo '###' `$c; spcli --json `$c 2>/dev/null || echo '{}'; done; echo '### dhcpd'; cat /etc/dhcp/dnsmasq-dhcpd.conf /var/service/dhcpd4/dhcpd.conf 2>/dev/null; true"

$sshArgs = @("-p", "$Port", "-o", "ConnectTimeout=15", "-o", "LogLevel=ERROR")
if ($Schluessel) { $sshArgs += @("-i", $Schluessel) }

Write-Host "Frage $Utm per SSH ab ..."
$roh = & ssh @sshArgs "$User@$Utm" $fern
if ($LASTEXITCODE -ne 0) {
    Write-Host "Fehler: SSH-Anmeldung an $User@${Utm}:$Port fehlgeschlagen." -ForegroundColor Red
    Write-Host "  SSH muss auf der UTM fuer diesen Rechner freigegeben sein (Netzwerk -> Servereinstellungen)." -ForegroundColor Red
    exit 1
}

# Auseinandernehmen: "### rule get" -> $teile["rule get"]
$teile = @{}
$aktuell = $null
foreach ($zeile in $roh) {
    if ($zeile -match '^### (.+)$') { $aktuell = $Matches[1].Trim(); $teile[$aktuell] = New-Object System.Collections.Generic.List[string]; continue }
    if ($aktuell) { $teile[$aktuell].Add($zeile) }
}

function Get-Inhalt([string]$Name) {
    if (-not $teile.ContainsKey($Name)) { return @() }
    try {
        $json = ($teile[$Name] -join "`n") | ConvertFrom-Json
        if ($json.result -and $json.result.content) { return @($json.result.content) }
    } catch { }
    return @()
}

# Der erste gefuellte Wert - PowerShell 5.1 kennt kein "??".
function Get-Erster {
    foreach ($wert in $args) {
        if ($null -ne $wert -and "$wert" -ne "" -and "$wert" -ne "unknown" -and "$wert" -ne "none") { return "$wert" }
    }
    return $null
}

$s = @{}
foreach ($z in Get-Inhalt "system info") { $s[$z.attribute] = $z.value }
if ($s.Count -eq 0) {
    Write-Host "Fehler: spcli lieferte keine Systeminformationen - ist $Utm eine Securepoint UTM, und darf $User spcli aufrufen?" -ForegroundColor Red
    exit 1
}

# Seriennummer: die des Geraets, bei einer virtuellen UTM die der Lizenz-Hardware.
$serie = Get-Erster $s["serialnumber"] $s["hardware serialnumber"]

# WAN: alles in der Zone "external". Der Zonenname ("internal", "dmz1")
# sagt mehr als "LAN3.33" - er wird in DokuVault die Bezeichnung des Netzes.
$zonen = Get-Inhalt "interface zone get"
$wan = @($zonen | Where-Object { $_.name -eq 'external' } | ForEach-Object { $_.interface })
$zone = @{}
foreach ($z in $zonen) { if ($z.interface -and @($z.flags).Count -eq 0) { $zone[$z.interface] = $z.name } }

$adressen = Get-Inhalt "interface address get"
$interfaces = @()
# VPN-Tunnel (tun, WireGuard, PPP) sind keine Netze der Doku - die VPNs
# stehen unten eigens.
foreach ($if in Get-Inhalt "interface get" | Where-Object { "$($_.type)" -notmatch '^(TUN|TAP|PPP|PPPOE|PPTP|WIREGUARD|WG)$' }) {
    $vlan = $null
    foreach ($o in @($if.options)) { if ("$o" -match '^vlan_id=(\d+)$') { $vlan = [int]$Matches[1] } }
    $ips = @($adressen | Where-Object { $_.device -eq $if.name -and "$($_.address)" -match '^[\d.]+/' } | ForEach-Object { $_.address })
    if ($ips.Count -eq 0) { $ips = @($null) }
    foreach ($ip in $ips) {
        $interfaces += [PSCustomObject]@{
            name   = Get-Erster $zone[$if.name] $if.name
            device = $if.name
            ip     = $ip
            vlan   = $vlan
            wan    = ($wan -contains $if.name)
        }
    }
}

$vpns = @()
foreach ($z in Get-Inhalt "ipsec get") { $vpns += [PSCustomObject]@{ type = "IPsec"; name = Get-Erster $z.name "IPsec"; remote = Get-Erster $z.remote } }
foreach ($z in Get-Inhalt "openvpn get") {
    # Ein OpenVPN-Server (SSL-VPN) hat keine Gegenstelle - dort sagen
    # Tunnelnetz und Port, was er ist.
    $gegenstelle = Get-Erster $z.remote
    if ($z.mode -eq 'SERVER') {
        $port = (@((Get-Erster $z.proto), (Get-Erster $z.local_port)) | Where-Object { $_ }) -join ' '
        $gegenstelle = Get-Erster ((@((Get-Erster $z.pool), (Get-Erster $port)) | Where-Object { $_ }) -join ' · ')
    }
    $vpns += [PSCustomObject]@{ type = "OpenVPN"; name = Get-Erster $z.name "OpenVPN"; remote = $gegenstelle }
}
# Je Peer: sein Endpunkt, bei einem Roadwarrior ohne Endpunkt sein Netz.
foreach ($wgIf in Get-Inhalt "wireguard get") {
    foreach ($z in @($wgIf.content)) {
        if (-not $z) { continue }
        $vpns += [PSCustomObject]@{ type = "WireGuard"; name = Get-Erster $z.peer_name "WireGuard"; remote = Get-Erster $z.peer_endpoint (@($z.peer_address_list) -join ', ') }
    }
}

# Portweiterleitungen sind Regeln mit DESTNAT: Ziel-Host und -Dienst stehen
# als Namen darin und werden hier zu Adresse und Port aufgeloest.
$knoten = @{}
foreach ($n in Get-Inhalt "node get") { $knoten[$n.name] = $n.address }
$dienste = @{}
foreach ($d in Get-Inhalt "service get") { $dienste[$d.name] = $d }
$portForwards = @()
foreach ($r in Get-Inhalt "rule get") {
    $flags = @($r.flags)
    if ($flags -notcontains 'DESTNAT' -or $flags -contains 'DISABLED') { continue }
    $dienst = $dienste[$r.service]
    $zielDienst = $dienste[(Get-Erster $r.nat_service $r.service)]
    $ziel = Get-Erster $knoten[$r.nat_node] $r.nat_node
    $portForwards += [PSCustomObject]@{
        description = Get-Erster $r.comment
        interface   = Get-Erster $r.dst
        protocol    = $(if ($dienst -and $dienst.proto) { "$($dienst.proto)".ToUpper() } else { $null })
        port        = $(if ($dienst) { Get-Erster (@($dienst.'dst-ports') -join ',') } else { $null })
        target      = $(if ($ziel) { $ziel -replace '/32$', '' } else { $null })
        target_port = $(if ($zielDienst) { Get-Erster (@($zielDienst.'dst-ports') -join ',') } else { $null })
    }
}

# Bei DHCP am WAN steht als Router nur die Schnittstelle, keine Adresse.
$gateways = @(Get-Inhalt "route get" | Where-Object { $_.dst -eq '0.0.0.0/0' } | ForEach-Object {
    [PSCustomObject]@{
        name    = Get-Erster $_.via_device $_.router "?"
        address = $(if ("$($_.router)" -match '^\d+(\.\d+){3}$') { $_.router } else { $null })
    }
} | Sort-Object name, address -Unique)

# DHCP-Bereiche samt DNS-Servern. Zwei Formate:
#  - Dnsmasq (UTM 14): "dhcp-range=set:3,10.0.33.10,10.0.33.100" und
#    "dhcp-option=tag:3,option:domain-name-servers,10.0.33.1" - Bereich und
#    DNS haengen ueber das Tag zusammen.
#  - ISC dhcpd (aeltere UTM): je "subnet"-Block "range a b;" und
#    "option domain-name-servers ...;".
$dhcpRanges = @()
$dnsFuer = @{}
$bereiche = @()
foreach ($zeile in @($teile["dhcpd"])) {
    if ($zeile -match '^dhcp-range=(.+)$') {
        $f = @($Matches[1] -split ',')
        $tag = ''
        if ($f[0] -match '^(set|tag):(.*)$') { $tag = $Matches[2]; $f = @($f | Select-Object -Skip 1) }
        if ($f[0] -match '^\d+(\.\d+){3}$') { $bereiche += [PSCustomObject]@{ start = $f[0]; end = $f[1]; tag = $tag } }
    }
    if ($zeile -match '^dhcp-option=' -and ($zeile -match 'domain-name-servers' -or $zeile -match '^dhcp-option=(tag:[^,]*,)?6,')) {
        $tag = ''
        if ($zeile -match 'tag:([^,]*)') { $tag = $Matches[1] }
        $dnsFuer[$tag] = @(($zeile -replace '^dhcp-option=', '') -split ',' | Where-Object { $_ -match '^\d+(\.\d+){3}$' })
    }
}
foreach ($b in $bereiche) {
    $dns = $(if ($dnsFuer.ContainsKey($b.tag)) { $dnsFuer[$b.tag] } elseif ($dnsFuer.ContainsKey('')) { $dnsFuer[''] } else { @() })
    $dhcpRanges += [PSCustomObject]@{ start = $b.start; end = $b.end; dns = @($dns) }
}

$start = $null; $ende = $null; $dns = @()
foreach ($zeile in @($teile["dhcpd"])) {
    if ($zeile -match '^\s*subnet\s') { $start = $null; $ende = $null; $dns = @() }
    if ($zeile -match '^\s*range\s+([\d.]+)\s+([\d.]+)') { $start = $Matches[1]; $ende = $Matches[2] }
    if ($zeile -match 'option\s+domain-name-servers\s+([^;]+)') {
        $dns = @($Matches[1] -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -match '^\d+(\.\d+){3}$' })
    }
    if ($zeile -match '^\s*}' -and $start) {
        $dhcpRanges += [PSCustomObject]@{ start = $start; end = $ende; dns = $dns }
        $start = $null
    }
}

# Die Lizenz laeuft in "daysleft" Tagen ab - als Datum in die Ablauf-Mail.
$bis = $null
if ("$($s['daysleft'])" -match '^\d+$') { $bis = (Get-Date).AddDays([int]$s['daysleft']).ToString('yyyy-MM-dd') }

$payload = [PSCustomObject]@{
    identifier         = "securepoint:" + (Get-Erster $serie $Utm)
    name               = Get-Erster $s["hostname"] $Utm
    manufacturer       = "Securepoint"
    model              = Get-Erster $s["devicetype"]
    serial             = $serie
    firmware           = Get-Erster $s["version"]
    subscription_until = $bis
    # Die Verwaltungsoberflaeche der UTM laeuft auf Port 11115.
    management_url     = "https://${Utm}:11115"
    interfaces         = $interfaces
    vpns               = $vpns
    port_forwards      = $portForwards
    gateways           = $gateways
    dhcp_ranges        = $dhcpRanges
} | ConvertTo-Json -Depth 6

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. $($interfaces.Count) Schnittstellen, $($vpns.Count) VPNs, $($portForwards.Count) Portweiterleitungen gemeldet."
