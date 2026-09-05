<#
  Auto-Dokumentation fuer Windows-Server  ->  DokuVault
  Auf dem Server als Administrator ausfuehren:
    .\windows-server-doku.ps1

  Ziel-URL ueberschreiben, ohne einen neuen Token zu erzeugen:
    .\windows-server-doku.ps1 -ApiUrl "https://doku.example/api/agent/windows-server"

  Legt den Rechner als Server an - nicht als Client. Der Windows-Client-Agent
  ist fuer Arbeitsplatzrechner gedacht; auf einem Server ausgefuehrt landet
  dieser unter "Clients", wo ihn niemand sucht.
#>
param(
    [string]$ApiUrl = "__API_URL__"
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"

$system = Get-CimInstance -ClassName Win32_ComputerSystem
$bios = Get-CimInstance -ClassName Win32_BIOS
$os = Get-CimInstance -ClassName Win32_OperatingSystem
$cpu = Get-CimInstance -ClassName Win32_Processor | Select-Object -First 1

# Dieselbe Kennung wie der Hyper-V-Agent: laeuft beides auf demselben Blech,
# soll es ein Server-Eintrag bleiben und nicht zwei werden.
$identifier = (Get-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Cryptography' -Name MachineGuid).MachineGuid

$ip = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '169.254.*' -and $_.InterfaceAlias -notmatch 'Loopback' } |
    Select-Object -First 1).IPAddress

<#
  Installierte Serverrollen (AD, DNS, DHCP, Dateiserver ...).

  Gemeldet wird Name, nicht DisplayName: der Anzeigename ist uebersetzt - ein
  deutsches Windows sagt "Active Directory-Domaenendienste", ein englisches
  "Active Directory Domain Services". Name ist in beiden Faellen
  "AD-Domain-Services", und danach ordnet DokuVault zu.

  Alle installierten Merkmale, nicht nur die vom Typ "Role": die Dateiserver-
  Rolle steckt als Rollendienst FS-FileServer unter "File and Storage
  Services", das seinerseits auf jedem Windows Server installiert ist. Nur den
  Typ "Role" zu melden haette also jeden Server zum Dateiserver erklaert.
  Welche Namen etwas bedeuten, entscheidet DokuVault - der Rest wird verworfen.

  Get-WindowsFeature gibt es nur auf Windows Server mit dem ServerManager-
  Modul; auf einem Client bleibt die Liste leer, statt das Script abbrechen
  zu lassen.
#>
$rollen = @()
try {
    Import-Module ServerManager -ErrorAction Stop
    $rollen = Get-WindowsFeature -ErrorAction Stop |
        Where-Object { $_.Installed } |
        Select-Object -ExpandProperty Name
} catch {
    Write-Host "Hinweis: Serverrollen konnten nicht gelesen werden ($($_.Exception.Message))."
}

$payload = [PSCustomObject]@{
    server = [PSCustomObject]@{
        identifier   = $identifier
        hostname     = $env:COMPUTERNAME
        manufacturer = $system.Manufacturer
        model        = $system.Model
        serial       = $bios.SerialNumber
        os           = $os.Caption
        ip           = $ip
        cpu          = "$($cpu.Name) ($($system.NumberOfLogicalProcessors) Kerne)"
        memory_gb    = [math]::Round($system.TotalPhysicalMemory / 1GB, 0)
        roles        = @($rollen)
    }
} | ConvertTo-Json -Depth 5

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. $($env:COMPUTERNAME) mit $(@($rollen).Count) Rollen gemeldet."
