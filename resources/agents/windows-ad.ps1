#Requires -Modules ActiveDirectory
<#
  Auto-Dokumentation fuer Windows Active Directory  ->  DokuVault
  Meldet die Domaene (NetBIOS, Funktionsebene, UPN-Suffixe, FSMO, DCs,
  DNS-Weiterleitungen, DHCP, Zertifizierungsstelle, Entra Connect) sowie
  Benutzer und Gruppen. Nur lesend.
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

# --- Domaene selbst ---
# Jede Abfrage einzeln abgesichert: Fehlt ein Modul (DnsServer, DhcpServer)
# oder ein Recht, bleibt nur dieses Feld leer - DokuVault laesst dann den
# vorhandenen Wert stehen, statt ihn zu loeschen.
$adDomain = Get-ADDomain
$adForest = Get-ADForest
$domain = $adDomain.DNSRoot

$domainControllers = @()
try {
    Get-ADDomainController -Filter * | ForEach-Object {
        $domainControllers += [PSCustomObject]@{
            name = $_.HostName
            ip   = $_.IPv4Address
        }
    }
} catch { Write-Warning "Domaenencontroller nicht abfragbar: $_" }

$dnsForwarders = @()
try {
    $dnsForwarders = @((Get-DnsServerForwarder -ErrorAction Stop).IPAddress | ForEach-Object { $_.IPAddressToString })
} catch { Write-Warning "DNS-Weiterleitungen nicht abfragbar (DnsServer-Modul fehlt?)" }

$dhcpServers = @()
try {
    $dhcpServers = @(Get-DhcpServerInDC -ErrorAction Stop | ForEach-Object { $_.DnsName })
} catch { Write-Warning "DHCP-Server nicht abfragbar (DhcpServer-Modul fehlt?)" }

# Zertifizierungsstellen stehen in der Konfigurationspartition.
$caHosts = @()
try {
    $configNc = (Get-ADRootDSE).configurationNamingContext
    $caHosts = @(Get-ADObject -SearchBase "CN=Enrollment Services,CN=Public Key Services,CN=Services,$configNc" `
        -Filter 'objectClass -eq "pKIEnrollmentService"' -Properties dNSHostName |
        ForEach-Object { $_.dNSHostName })
} catch { Write-Warning "Zertifizierungsstellen nicht abfragbar: $_" }

# Entra Connect legt ein Konto MSOL_... an; in dessen Beschreibung steht der
# Server ("... running on computer SYNC01 ...").
$entraConnectHost = $null
try {
    $msol = Get-ADUser -Filter 'SamAccountName -like "MSOL_*"' -Properties Description | Select-Object -First 1
    if ($msol -and $msol.Description -match 'computer\s+([^\s]+)') {
        $entraConnectHost = $Matches[1].TrimEnd('.', ',')
    }
} catch { Write-Warning "Entra Connect nicht erkennbar: $_" }

$ad = [PSCustomObject]@{
    netbios            = $adDomain.NetBIOSName
    domain_mode        = "$($adDomain.DomainMode)"
    upn_suffixes       = @($adForest.UPNSuffixes)
    fsmo               = [PSCustomObject]@{
        pdc            = $adDomain.PDCEmulator
        rid            = $adDomain.RIDMaster
        infrastructure = $adDomain.InfrastructureMaster
        schema         = $adForest.SchemaMaster
        naming         = $adForest.DomainNamingMaster
    }
    domain_controllers = $domainControllers
    dns_forwarders     = $dnsForwarders
    dhcp_servers       = $dhcpServers
    ca_hosts           = $caHosts
    entra_connect_host = $entraConnectHost
}

$payload = [PSCustomObject]@{
    domain = $domain
    ad     = $ad
    users  = $users
    groups = $groups
} | ConvertTo-Json -Depth 5

Write-Host "Sende Dokumentation an $ApiUrl ..."
$antwort = Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. Domaene $domain, $($users.Count) Benutzer, $($groups.Count) Gruppen gemeldet."

# Maschinen, die der DC nennt, die in DokuVault aber noch nicht als Server
# oder VM dokumentiert sind - nach dem Anlegen verknuepft der naechste Lauf sie.
if ($antwort.unmatched_hosts -and $antwort.unmatched_hosts.Count -gt 0) {
    Write-Host "Nicht zugeordnet (in DokuVault als Server/VM anlegen): $($antwort.unmatched_hosts -join ', ')" -ForegroundColor Yellow
}
