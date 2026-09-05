<#
  Auto-Dokumentation fuer Windows-Arbeitsplatzrechner  ->  DokuVault
  Auf dem Client ausfuehren:
    .\windows-client-doku.ps1

  Ziel-URL ueberschreiben, ohne einen neuen Token zu erzeugen:
    .\windows-client-doku.ps1 -ApiUrl "https://doku.example/api/agent/windows-client"
#>
param(
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"

$system = Get-CimInstance -ClassName Win32_ComputerSystem
$bios = Get-CimInstance -ClassName Win32_BIOS
$os = Get-CimInstance -ClassName Win32_OperatingSystem

# Stabil pro Windows-Installation, ueberlebt auch einen Hostname-Wechsel -
# anders als der Hostname selbst, der sich am naechsten Umzug aendern kann.
$identifier = (Get-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Cryptography' -Name MachineGuid).MachineGuid

$ip = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '169.254.*' -and $_.InterfaceAlias -notmatch 'Loopback' } |
    Select-Object -First 1).IPAddress

$payload = [PSCustomObject]@{
    client = [PSCustomObject]@{
        identifier   = $identifier
        hostname     = $env:COMPUTERNAME
        manufacturer = $system.Manufacturer
        model        = $system.Model
        serial       = $bios.SerialNumber
        os           = $os.Caption
        ip           = $ip
    }
} | ConvertTo-Json -Depth 5

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. $($env:COMPUTERNAME) gemeldet."
