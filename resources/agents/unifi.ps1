<#
  Auto-Dokumentation fuer UniFi  ->  DokuVault
  Auf einem Rechner ausfuehren, der den UniFi-Controller erreicht:
    .\unifi-doku.ps1 -Controller "https://unifi.local" -User "doku" -Password "..."

  Meldet Switches, Accesspoints und WLANs. Rein lesend - es wird nichts am
  Controller veraendert. Ein Konto mit reinen Leserechten genuegt.

  Die Zugangsdaten des Controllers werden hier beim Aufruf mitgegeben und
  NICHT in DokuVault gespeichert. Das WLAN-Kennwort wird bewusst nicht
  ausgelesen - wie beim AD-Agenten bleiben Kennwoerter manuell gepflegt.
#>
param(
    [Parameter(Mandatory = $true)][string]$Controller,
    [Parameter(Mandatory = $true)][string]$User,
    [Parameter(Mandatory = $true)][string]$Password,
    [string]$Site = "default",
    [string]$ApiUrl = "__API_URL__",
    [switch]$ZertifikatIgnorieren
)

$ErrorActionPreference = "Stop"
$Token = "__AGENT_TOKEN__"
$Controller = $Controller.TrimEnd('/')

<#
  Ein UniFi-Controller bringt ab Werk ein selbst signiertes Zertifikat mit.
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
        [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
    }
}

$anmeldung = @{ username = $User; password = $Password } | ConvertTo-Json

<#
  Zwei Bauarten von Controller, zwei Anmeldewege:
  - UniFi OS (UDM, UDM-Pro, Cloud Key Gen2+): /api/auth/login, danach liegen
    die Netzwerk-Endpunkte unter /proxy/network.
  - Klassischer Controller (Software auf Linux/Windows): /api/login, die
    Endpunkte liegen direkt unter der Wurzel.
  Erst UniFi OS versuchen, sonst klassisch - so laeuft dasselbe Script auf
  beiden, ohne dass der Nutzer die Bauart kennen muss.
#>
$basis = $null
try {
    Invoke-RestMethod -Method Post -Uri "$Controller/api/auth/login" -Body $anmeldung `
        -ContentType "application/json" -SessionVariable sitzung @RestExtra | Out-Null
    $basis = "$Controller/proxy/network"
    Write-Host "Angemeldet (UniFi OS)."
} catch {
    Invoke-RestMethod -Method Post -Uri "$Controller/api/login" -Body $anmeldung `
        -ContentType "application/json" -SessionVariable sitzung @RestExtra | Out-Null
    $basis = $Controller
    Write-Host "Angemeldet (klassischer Controller)."
}

function Get-UnifiDaten([string]$pfad) {
    $antwort = Invoke-RestMethod -Method Get -Uri "$basis$pfad" -WebSession $sitzung @RestExtra
    return @($antwort.data)
}

function Get-Verschluesselung($wlan) {
    switch ($wlan.security) {
        'open' { return 'Offen' }
        'wpaeap' { return 'WPA2-Enterprise' }
        'wpapsk' {
            if ($wlan.wpa3_support -or $wlan.wpa_mode -eq 'wpa3') { return 'WPA3-PSK' }
            if ($wlan.wpa_mode -eq 'wpa2') { return 'WPA2-PSK' }
            return 'WPA-PSK'
        }
        default { return $wlan.security }
    }
}

$geraete = Get-UnifiDaten "/api/s/$Site/stat/device"
$wlanconf = Get-UnifiDaten "/api/s/$Site/rest/wlanconf"

# Die MAC-Adresse als Kennung: sie bleibt, auch wenn das Geraet umbenannt oder
# in einen anderen Standort umgehaengt wird.
function ConvertTo-Geraet($g) {
    # Ein frisch adoptiertes Geraet hat noch keinen Namen - dann steht die MAC
    # da, damit der Eintrag ueberhaupt eine Beschriftung bekommt. Als eigene
    # Zuweisung und nicht als "if" im Hashtable: das kann PowerShell 5.1 nicht,
    # und 5.1 ist das, was auf einem Windows-Server ohne Nachruesten laeuft.
    $name = $g.name
    if (-not $name) { $name = $g.mac }

    return [PSCustomObject]@{
        identifier   = $g.mac
        name         = $name
        manufacturer = 'Ubiquiti'
        model        = $g.model
        serial       = $g.serial
        ip           = $g.ip
    }
}

$switches = @($geraete | Where-Object { $_.type -eq 'usw' } | ForEach-Object { ConvertTo-Geraet $_ })
$accesspoints = @($geraete | Where-Object { $_.type -eq 'uap' } | ForEach-Object { ConvertTo-Geraet $_ })

$wifis = @($wlanconf | ForEach-Object {
    [PSCustomObject]@{
        identifier = $_._id
        ssid       = $_.name
        encryption = Get-Verschluesselung $_
    }
})

$payload = [PSCustomObject]@{
    site         = $Site
    switches     = $switches
    accesspoints = $accesspoints
    wifis        = $wifis
} | ConvertTo-Json -Depth 5

Write-Host "Sende Dokumentation an $ApiUrl ..."
Invoke-RestMethod -Method Post -Uri $ApiUrl `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))

Write-Host "Fertig. $($switches.Count) Switches, $($accesspoints.Count) Accesspoints, $($wifis.Count) WLANs gemeldet."
