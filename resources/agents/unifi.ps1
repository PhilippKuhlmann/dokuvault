<#
  Auto-Dokumentation fuer UniFi  ->  DokuVault
  Auf einem Rechner ausfuehren, der den UniFi-Controller erreicht:
    .\unifi-doku.ps1 -Controller "https://unifi.local" -User "doku" -Password "..." -Site "Kunde A"

  -Site nimmt den internen Namen oder den Anzeigenamen. Welche es gibt, zeigt
  derselbe Aufruf mit -Sites statt -Site.

  Bei mehreren Sites ist -Site Pflicht. Ein Token gehoert zu genau einem
  Kunden - griffe das Script von sich aus die falsche Site ab, landeten dessen
  Geraete in der Dokumentation eines anderen.

  Meldet Switches, Accesspoints und WLANs. Rein lesend - es wird nichts am
  Controller veraendert. Ein Konto mit reinen Leserechten genuegt.

  Die Zugangsdaten des Controllers werden hier beim Aufruf mitgegeben und
  NICHT in DokuVault gespeichert.

  Die WLAN-Passphrase dagegen schon: sie steht im Klartext in der
  Controller-Konfiguration, DokuVault hat dafuer eine verschluesselte Spalte,
  und in einer Dokumentation ist genau sie das, was man nachschlaegt. Wer das
  nicht will, gibt -OhneKennwoerter mit.
#>
param(
    [Parameter(Mandatory = $true)][string]$Controller,
    [Parameter(Mandatory = $true)][string]$User,
    [Parameter(Mandatory = $true)][string]$Password,
    [string]$Site = "",
    [switch]$Sites,
    [switch]$OhneKennwoerter,
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
$csrf = $null

try {
    $anmeldeantwort = Invoke-WebRequest -Method Post -Uri "$Controller/api/auth/login" -Body $anmeldung `
        -ContentType "application/json" -SessionVariable sitzung @RestExtra
    $basis = "$Controller/proxy/network"

    <#
      UniFi OS laesst den Sitzungskeks allein nicht genuegen: jede Anfrage
      unter /proxy/network braucht zusaetzlich den CSRF-Token. Ohne ihn
      antwortet der Controller mit 403, obwohl die Anmeldung geklappt hat.

      Er steht in der Kopfzeile der Anmeldeantwort. Aeltere Firmware schickt
      ihn dort nicht mit - dann steckt er in der Nutzlast des TOKEN-Kekses,
      der ein JWT ist.
    #>
    # Ueber die Schluessel laufen statt $Headers['X-CSRF-Token'] zu lesen: in
    # PowerShell 5.1 wirft der Indexer bei einem fehlenden Schluessel, statt
    # $null zu liefern - und die Schreibweise der Kopfzeile wechselt je nach
    # Firmware. -ieq vergleicht ohne Ruecksicht darauf.
    foreach ($schluessel in $anmeldeantwort.Headers.Keys) {
        if ($schluessel -ieq 'X-CSRF-Token') {
            $csrf = $anmeldeantwort.Headers[$schluessel]
            break
        }
    }
    if ($csrf -is [array]) { $csrf = $csrf[0] }

    if (-not $csrf) {
        $keks = $sitzung.Cookies.GetCookies([uri]$Controller) |
            Where-Object { $_.Name -eq 'TOKEN' } | Select-Object -First 1
        if ($keks) {
            try {
                $teil = ($keks.Value -split '\.')[1] -replace '-', '+' -replace '_', '/'
                # Base64 will die Laenge durch vier teilbar haben; im JWT fehlt
                # die Auffuellung.
                switch ($teil.Length % 4) { 2 { $teil += '==' } 3 { $teil += '=' } }
                $csrf = ([System.Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($teil)) |
                    ConvertFrom-Json).csrfToken
            } catch {
                $csrf = $null
            }
        }
    }

    Write-Host "Angemeldet (UniFi OS)."
} catch {
    Invoke-RestMethod -Method Post -Uri "$Controller/api/login" -Body $anmeldung `
        -ContentType "application/json" -SessionVariable sitzung @RestExtra | Out-Null
    $basis = $Controller
    Write-Host "Angemeldet (klassischer Controller)."
}

function Get-UnifiDaten([string]$pfad) {
    $kopf = @{}
    if ($csrf) { $kopf['X-Csrf-Token'] = $csrf }

    try {
        $antwort = Invoke-RestMethod -Method Get -Uri "$basis$pfad" -WebSession $sitzung `
            -Headers $kopf @RestExtra
    } catch {
        # Der Controller schreibt in die Antwort, was ihm fehlt. Nur den
        # Statuscode zu zeigen laesst den Nutzer raten.
        $code = $_.Exception.Response.StatusCode.value__
        Write-Host "Fehler: $basis$pfad antwortete mit HTTP $code." -ForegroundColor Red
        if ($_.ErrorDetails.Message) {
            Write-Host "  Antwort: $($_.ErrorDetails.Message)" -ForegroundColor Red
        }
        if ($code -eq 403) {
            Write-Host "  403 bei UniFi OS heisst meist eines von beiden:" -ForegroundColor Red
            Write-Host "      - Das Konto ist ein Ubiquiti-Cloud-Konto. Die Schnittstelle braucht" -ForegroundColor Red
            Write-Host "        einen lokalen Administrator (UniFi OS: Einstellungen -> Admins," -ForegroundColor Red
            Write-Host "        Zugriff 'Nur lokal')." -ForegroundColor Red
            Write-Host "      - Dem Konto fehlen die Leserechte fuer diese Site." -ForegroundColor Red
        }
        if ($code -eq 404) {
            if ($pfad -like '*/api/s/*') {
                Write-Host "  404: die Site '$Site' gibt es nicht. Mit -Sites die vorhandenen anzeigen." -ForegroundColor Red
            } else {
                Write-Host "  404: diesen Endpunkt gibt es nicht. Zeigt die Controller-URL wirklich auf UniFi?" -ForegroundColor Red
            }
        }
        exit 1
    }

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

<#
  Welche Sites das Konto sehen darf. 'name' ist der interne Schluessel, der in
  der URL steht (oft eine Zufallsfolge wie 'a1b2c3d4'), 'desc' der Anzeigename
  aus der Oberflaeche. Waehlen soll man mit dem, was man vor sich sieht -
  deshalb trifft -Site beides.
#>
$alleSites = Get-UnifiDaten "/api/self/sites"

function Show-Sites {
    foreach ($eintrag in $alleSites) {
        Write-Host ("  {0}   {1}" -f $eintrag.name, $eintrag.desc)
    }
}

if ($Sites) {
    Write-Host "Sites auf diesem Controller:"
    Show-Sites
    exit 0
}

if ($Site) {
    # -eq vergleicht bei Zeichenketten ohne Ruecksicht auf Gross- und
    # Kleinschreibung - genau richtig fuer einen Namen, den jemand abtippt.
    $treffer = $alleSites | Where-Object { $_.name -eq $Site -or $_.desc -eq $Site } | Select-Object -First 1

    if (-not $treffer) {
        Write-Host "Fehler: keine Site '$Site' auf diesem Controller." -ForegroundColor Red
        Write-Host "Vorhanden sind:"
        Show-Sites
        exit 1
    }

    $Site = $treffer.name
    $anzeige = $treffer.desc
} elseif ($alleSites.Count -eq 1) {
    $Site = $alleSites[0].name
    $anzeige = $alleSites[0].desc
} else {
    # Nicht raten: auf einem Controller mit mehreren Sites steckt oft je Kunde
    # eine. Der Agent-Token gehoert zu genau einem Kunden - die falsche Site
    # abzugreifen hiesse, fremde Geraete in dessen Dokumentation zu schreiben.
    Write-Host "Fehler: dieser Controller hat $($alleSites.Count) Sites. Bitte mit -Site waehlen:" -ForegroundColor Red
    Show-Sites
    exit 1
}

Write-Host "Site: $Site ($anzeige)"

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
    $eintrag = [ordered]@{
        identifier = $_._id
        ssid       = $_.name
        encryption = Get-Verschluesselung $_
    }

    # x_passphrase gibt es nur bei WPA-PSK. Ein Enterprise-WLAN hat keine -
    # dann faellt das Feld weg, statt leer uebertragen zu werden.
    if (-not $OhneKennwoerter -and $_.x_passphrase) {
        $eintrag['password'] = $_.x_passphrase
    }

    [PSCustomObject]$eintrag
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
