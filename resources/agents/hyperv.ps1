#Requires -Modules Hyper-V
<#
  Auto-Dokumentation fuer Hyper-V  ->  DokuVault
  Auf dem Hyper-V-Host als Administrator ausfuehren:
    .\hyperv-doku.ps1

  Ziel-URL ueberschreiben, ohne einen neuen Token zu erzeugen:
    .\hyperv-doku.ps1 -ApiUrl "https://doku.example/api/agent/hyperv"

  Meldet den Host als Server und jede virtuelle Maschine als eigenen Eintrag.
  Rein lesend: keine VM wird gestartet, angehalten oder veraendert.
#>
param(
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"

Import-Module Hyper-V

$system = Get-CimInstance -ClassName Win32_ComputerSystem
$bios = Get-CimInstance -ClassName Win32_BIOS
$os = Get-CimInstance -ClassName Win32_OperatingSystem
$cpu = Get-CimInstance -ClassName Win32_Processor | Select-Object -First 1

# Stabil pro Windows-Installation, ueberlebt auch einen Hostname-Wechsel -
# dieselbe Kennung wie beim Windows-Server-Agenten, damit beide denselben
# Server-Eintrag treffen statt zwei anzulegen.
$identifier = (Get-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Cryptography' -Name MachineGuid).MachineGuid

$ip = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '169.254.*' -and $_.InterfaceAlias -notmatch 'Loopback' } |
    Select-Object -First 1).IPAddress

<#
  Das Gastbetriebssystem melden die Integrationsdienste ueber den
  KVP-Austausch. Fehlen sie (Linux ohne hv_kvp_daemon, VM aus), liefert das
  nichts - dann bleibt das Feld leer, statt einen Wert zu raten.
#>
function Get-GastBetriebssystem($vm) {
    try {
        $kvp = Get-CimInstance -Namespace root\virtualization\v2 -ClassName Msvm_ComputerSystem -Filter "ElementName='$($vm.Name)'" |
            Get-CimAssociatedInstance -ResultClassName Msvm_KvpExchangeComponent -ErrorAction Stop
        foreach ($eintrag in $kvp.GuestIntrinsicExchangeItems) {
            $xml = [xml]$eintrag
            $name = ($xml.INSTANCE.PROPERTY | Where-Object { $_.NAME -eq 'Name' }).VALUE
            if ($name -eq 'OSName') {
                return ($xml.INSTANCE.PROPERTY | Where-Object { $_.NAME -eq 'Data' }).VALUE
            }
        }
    } catch {
        return $null
    }
    return $null
}

$guests = @()
foreach ($vm in Get-VM) {
    # Erste echte IPv4-Adresse ueber alle Netzwerkkarten der VM. 169.254.*
    # bedeutet "kein DHCP erreicht" und taugt nicht zur Dokumentation.
    $vmIp = $vm | Get-VMNetworkAdapter | Select-Object -ExpandProperty IPAddresses |
        Where-Object { $_ -match '^\d+\.\d+\.\d+\.\d+$' -and $_ -notlike '169.254.*' } |
        Select-Object -First 1

    $guests += [PSCustomObject]@{
        identifier = $vm.Id.Guid
        name       = $vm.Name
        type       = 'hyperv'
        os         = Get-GastBetriebssystem $vm
        ip         = $vmIp
        status     = $vm.State.ToString()
        cores      = [int]$vm.ProcessorCount
        memory_gb  = [math]::Round($vm.MemoryStartup / 1GB, 0)
    }
}

$payload = [PSCustomObject]@{
    host = [PSCustomObject]@{
        identifier   = $identifier
        hostname     = $env:COMPUTERNAME
        manufacturer = $system.Manufacturer
        model        = $system.Model
        serial       = $bios.SerialNumber
        os           = $os.Caption
        ip           = $ip
        cpu          = "$($cpu.Name) ($($system.NumberOfLogicalProcessors) Kerne)"
        memory_gb    = [math]::Round($system.TotalPhysicalMemory / 1GB, 0)
    }
    guests = $guests
} | ConvertTo-Json -Depth 5

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. $($env:COMPUTERNAME) und $($guests.Count) virtuelle Maschinen gemeldet."
