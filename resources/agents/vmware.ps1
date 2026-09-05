<#
  Auto-Dokumentation fuer VMware vSphere  ->  DokuVault
  Auf einem Rechner ausfuehren, der vCenter erreicht:
    .\vmware-doku.ps1 -Server "vcenter.local" -User "doku@vsphere.local" -Password "..."

  Meldet jeden ESXi-Host als Server und jede virtuelle Maschine als eigenen
  Eintrag. Rein lesend - eine Rolle "Read-only" genuegt, es wird nichts an der
  Umgebung veraendert und keine VM angefasst.

  Ueber die vSphere-REST-Schnittstelle, nicht ueber PowerCLI: so laeuft das
  Script ohne Nachinstallation auf jedem Windows-Rechner.

  Voraussetzung ist vCenter. Ein einzeln stehender ESXi-Host bringt die
  /api/vcenter/*-Endpunkte nicht mit - solche Hosts dokumentiert man ueber das
  vCenter, dem sie angehoeren, oder von Hand.

  Die vCenter-Zugangsdaten werden hier beim Aufruf mitgegeben und NICHT in
  DokuVault gespeichert.
#>
param(
    [Parameter(Mandatory = $true)][string]$Server,
    [Parameter(Mandatory = $true)][string]$User,
    [Parameter(Mandatory = $true)][string]$Password,
    [string]$ApiUrl = "__API_URL__",
    [switch]$ZertifikatIgnorieren
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"
$Server = $Server -replace '^https?://', '' -replace '/+$', ''
$basis = "https://$Server"

# vCenter bringt ab Werk ein selbst signiertes Zertifikat mit; der Weg, die
# Pruefung abzuschalten, ist in PowerShell 5.1 ein anderer als ab 6.
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
        [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
    }
}

# Anmeldung: Basic-Auth einmal gegen /api/session, danach traegt jede Anfrage
# nur noch die Sitzungskennung.
$basic = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes("${User}:${Password}"))
$sitzung = Invoke-RestMethod -Method Post -Uri "$basis/api/session" `
    -Headers @{ Authorization = "Basic $basic" } @RestExtra

$kopf = @{ 'vmware-api-session-id' = $sitzung }

function Get-VSphere([string]$pfad) {
    return Invoke-RestMethod -Method Get -Uri "$basis$pfad" -Headers $kopf @RestExtra
}

<#
  guest_OS ist eine Kennung wie WINDOWS_SERVER_2019 oder RHEL_8_64. Daraus
  wird die Familie abgeleitet - genauso, wie der Proxmox-Agent aus "win11"
  oder "l26" verfaehrt. Ein Katalogeintrag je vSphere-Kennung waere ein
  Wildwuchs, den niemand pflegt.
#>
function Get-Betriebssystem([string]$kennung) {
    if (-not $kennung) { return $null }
    switch -Regex ($kennung) {
        '^WINDOWS' { return 'Windows' }
        '^(RHEL|CENTOS|UBUNTU|DEBIAN|SLES|SUSE|OPENSUSE|FEDORA|ORACLE_LINUX|AMAZONLINUX|ROCKY|ALMA|ASIANUX|COREOS|VMWARE_PHOTON|OTHER.*LINUX|.*_64_GUEST)' { return 'Linux' }
        '^FREEBSD' { return 'FreeBSD' }
        '^SOLARIS' { return 'Solaris' }
        '^DARWIN' { return 'macOS' }
        default { return $null }
    }
}

$hosts = @(Get-VSphere "/api/vcenter/host")
$gemeldet = 0
$vmAnzahl = 0

foreach ($h in $hosts) {
    # VMs je Host abfragen statt alle auf einmal: nur so ist bekannt, welche
    # VM auf welchem Host laeuft - die Sammelabfrage liefert das nicht mit.
    $vms = @(Get-VSphere "/api/vcenter/vm?hosts=$($h.host)")

    $guests = @()
    foreach ($vm in $vms) {
        $gastOs = $null
        try {
            $detail = Get-VSphere "/api/vcenter/vm/$($vm.vm)"
            $gastOs = Get-Betriebssystem $detail.guest_OS
        } catch {
            # Eine VM, die gerade migriert oder gesperrt ist, liefert keine
            # Details. Das ist kein Grund, den ganzen Lauf abzubrechen.
        }

        $guests += [PSCustomObject]@{
            identifier = $vm.vm
            name       = $vm.name
            type       = 'vmware'
            os         = $gastOs
            status     = $vm.power_state
            cores      = [int]$vm.cpu_count
            memory_gb  = [math]::Round($vm.memory_size_MiB / 1024, 0)
        }
    }

    $payload = [PSCustomObject]@{
        host = [PSCustomObject]@{
            identifier = $h.host
            hostname   = $h.name
            os         = 'VMware ESXi'
        }
        guests = $guests
    } | ConvertTo-Json -Depth 5

    Invoke-RestMethod -Method Post -Uri $ApiUrl `
        -Headers @{ Authorization = "Bearer $Token" } `
        -ContentType "application/json; charset=utf-8" `
        -Body ([System.Text.Encoding]::UTF8.GetBytes($payload)) | Out-Null

    Write-Host "  $($h.name): $($guests.Count) VMs gemeldet."
    $gemeldet++
    $vmAnzahl += $guests.Count
}

# Sitzung wieder schliessen, statt sie bis zum Ablauf offen liegen zu lassen.
try { Invoke-RestMethod -Method Delete -Uri "$basis/api/session" -Headers $kopf @RestExtra | Out-Null } catch { }

Write-Host "Fertig. $gemeldet Hosts und $vmAnzahl virtuelle Maschinen gemeldet."
