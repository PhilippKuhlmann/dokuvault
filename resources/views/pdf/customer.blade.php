<!DOCTYPE html>
<html lang="de">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Dokumentation - {{ $customer->name }}</title>

    <style>
        {{--
            Dieselbe Schrift wie die Anwendung.

            TTF und nicht die woff2 der Oberflaeche: DomPDF laedt aus einem
            @font-face ausschliesslich Quellen im Format truetype (siehe
            Css/Stylesheet::_parse_font_face) und ignoriert alles andere
            stillschweigend - das Blatt saehe dann aus wie ein Serienbrief.

            Die drei Dateien sind feste Schnitte aus der variablen Fassung und
            tragen den vollen Zeichenvorrat. Die woff2 der Oberflaeche sind
            nach Zeichenbereich geteilt, was im PDF nicht ginge: DomPDF kennt
            kein unicode-range und nimmt je Gewicht genau eine Datei.

            Auch der 700er ist angemeldet, obwohl das Blatt ihn heute nirgends
            anfordert. Ohne ihn faende ein spaeteres <strong> in dieser
            Familie keinen passenden Schnitt, und DomPDF fiele fuer die Stelle
            auf seine Standardschrift zurueck.
        --}}
        @font-face { font-family: 'Space Grotesk'; font-weight: 400; src: url('fonts/SpaceGrotesk-Regular.ttf'); }
        @font-face { font-family: 'Space Grotesk'; font-weight: 600; src: url('fonts/SpaceGrotesk-Semibold.ttf'); }
        @font-face { font-family: 'Space Grotesk'; font-weight: 700; src: url('fonts/SpaceGrotesk-Bold.ttf'); }

        {{--
            Die Farben stehen als feste Werte da, weil DomPDF keine
            CSS-Variablen aufloest. Es sind dieselben wie in der Oberflaeche,
            hier einmal benannt, damit eine Aenderung am Token nicht
            unbemerkt am Blatt vorbeilaeuft:

              #1f3d6e  chathams-blue-800   Ueberschriften
              #f1f6fc  chathams-blue-50    Kartenkopf
              #3391f0  cerulean-500        Linien und Akzent
              #111827  gray-900            Fliesstext
              #6b7280  gray-500            Beschriftungen
              #9ca3af  gray-400            Nebensaechliches
              #e5e7eb  gray-200            Raender
              #f3f4f6  gray-100            feine Trennlinien
              #374151  gray-700            Inhaltsverzeichnis
        --}}

        * { box-sizing: border-box; }
        html { font-family: 'Space Grotesk'; color: #111827; font-size: 12px; }
        /* In DomPDF wird der body-Rand als Seitenrand auf JEDER Seite angewendet
           -> Druckränder für Drucker ohne Randlosdruck. Oben und unten mehr:
              dort zeichnet der Job Kopf- und Fußzeile auf die Leinwand, und was
              dort steht, weiß vom Textfluss nichts. */
        body { margin: 17mm 12mm 15mm; }

        /* Maschinenwerte - IP, MAC, Seriennummer, Schlüssel. DejaVu Sans Mono
           liegt DomPDF bei, es muss nichts angemeldet werden. Dafür ist die
           Schrift breiter als Space Grotesk und braucht einen Punkt weniger.
           Wer eine IP abtippt, verwechselt in einer Festbreitenschrift keine
           0 mit einem O. */
        .mono { font-family: 'DejaVu Sans Mono'; font-size: 9.5px; }

        /* Deckblatt. Der Kundenname steht groß, nicht das Wort
           "Dokumentation": auf dem Stapel sucht man den Kunden. */
        .cover { page-break-after: always; }
        .cover-app { font-size: 10px; font-weight: 600; letter-spacing: 1.6px; text-transform: uppercase; color: #3391f0; }
        .cover-rule { height: 2px; background: #1f3d6e; margin: 7px 0 0; }
        .cover-eyebrow { margin-top: 360px; font-size: 11px; letter-spacing: 2.6px; text-transform: uppercase; color: #9ca3af; }
        .cover-customer { margin-top: 10px; font-size: 44px; font-weight: 600; color: #1f3d6e; line-height: 1.12; }
        .cover-date { margin-top: 16px; font-size: 11px; color: #6b7280; }
        .cover-note {
            margin-top: 330px; width: 420px; padding: 1px 0 1px 12px;
            border-left: 3px solid #3391f0; color: #6b7280; font-size: 10px; line-height: 1.55;
        }

        /* Inhalt: Kapitel fett, Abschnitte darunter eingerückt */
        .toc { page-break-after: always; }
        .toc-title { font-weight: 600; font-size: 24px; color: #1f3d6e; margin-bottom: 10px; page-break-after: avoid; }
        .toc table { width: 100%; border-collapse: collapse; }
        .toc-columns { table-layout: fixed; }
        .toc-column { vertical-align: top; }
        .toc-gap { width: 7%; }
        .toc td { padding: 2.5px 0; line-height: 13px; font-size: 10.5px; }
        .toc td.toc-page { text-align: right; width: 36px; color: #9ca3af; }
        .toc-chapter td { padding-top: 9px; font-weight: 600; font-size: 12px; color: #1f3d6e; border-bottom: 1px solid #e5e7eb; }
        .toc-section td { color: #374151; border-bottom: 1px solid #f3f4f6; }
        .toc-section td.toc-name { padding-left: 14px; }

        .page-break { page-break-after: always; }

        /* Kapitel: die zwölf Gruppen, nach denen das Blatt ohnehin geordnet
           war - bisher nur als Kommentar in der Vorlage. Sichtbar geben sie
           60 Seiten eine Gliederung, in die man springen kann. */
        .chapter { margin: 13mm 0 7mm; page-break-after: avoid; page-break-inside: avoid; }
        .chapter-first { margin-top: 0; }
        .chapter-rule { height: 2px; background: #1f3d6e; margin-bottom: 7px; }
        .chapter-title { font-size: 26px; font-weight: 600; color: #1f3d6e; }

        /* Abschnitte laufen durch, statt jeder auf einer eigenen Seite zu
           beginnen: bei 45 Abschnitten waren zwei Drittel jeder Seite leer.
           Die Überschrift nimmt den Umbruch mit (page-break-after: avoid),
           damit sie nicht allein am Seitenfuß stehen bleibt. */
        .section { margin-bottom: 7mm; }
        .heading {
            font-weight: 600; font-size: 17px; color: #1f3d6e; border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px; margin-bottom: 8px; page-break-after: avoid;
        }

        /* Keine Kästen mehr. Rahmen, getönter Kopf und innere Tabelle waren
           drei Lagen Rahmenwerk für zwei Ebenen Information; über 60 Seiten
           las sich das Blatt als Stapel gleicher Behälter. Jetzt trägt ein
           blauer Strich am Titel die Gliederung. */
        .card { margin-bottom: 6mm; page-break-inside: avoid; }
        .card-title { font-weight: 600; font-size: 12.5px; color: #1f3d6e; border-left: 2px solid #3391f0; padding-left: 8px; margin-bottom: 7px; }

        .groups { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .group-cell { vertical-align: top; padding: 0 16px 7px 10px; }
        .card-table-title { font-weight: 600; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.9px; color: #9ca3af; margin-bottom: 4px; }
        /* table-layout: fixed haelt die Tabelle in der Breite ihrer Gruppe.
           Mit automatischer Breite nahm die Wertespalte sich so viel, wie ihr
           laengster Eintrag brauchte, und sprengte den Kasten. */
        .group-cell table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .group-cell td { padding: 1.5px 0; font-size: 10.5px; vertical-align: top; line-height: 1.35; }
        /* 42 statt 45 Prozent: die Beschriftungen sind kurz, die Werte nicht.
           overflow-wrap ist nur das Netz darunter - "Verschlüsselungscode"
           ist laenger als die Spalte und stand sonst ueber seinem Wert. Ein
           Umbruch im Wort ist haesslich, uebereinander gedruckter Text ist
           schlimmer. */
        .group-cell td.key { color: #6b7280; width: 42%; padding-right: 10px; overflow-wrap: break-word; }
        /* Nur der Wert bricht im Wort. Genau daran lag die Ueberlappung:
           "DeutschlandLAN Glasfaser 1000" fand keine Bruchstelle, sprengte
           die Gruppe und stand quer ueber "WAN-IP". */
        .group-cell td.val { color: #111827; overflow-wrap: break-word; }

        .clear { clear: both; }
        /* Kein font-style: italic - von 'Space Grotesk' ist nur der gerade
           Schnitt angemeldet, und DomPDF nimmt dafuer still seine eigene
           Serifenschrift. Das stach aus dem Blatt heraus. */
        .empty { color: #9ca3af; font-size: 11px; padding: 0 0 4px 10px; }
        .empty-card { color: #9ca3af; font-size: 10.5px; padding-left: 10px; }

        /* Dosenbelegung eines Patchfelds */
        .ports-block { page-break-inside: avoid; margin: 0 0 6mm 10px; }
        .ports-caption { font-weight: 600; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.9px; color: #9ca3af; margin-bottom: 4px; }
        .ports { width: 100%; border-collapse: collapse; font-size: 10px; }
        .ports th { text-align: left; color: #9ca3af; border-bottom: 1px solid #e5e7eb; padding: 3px 8px 3px 0; font-weight: 600; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.9px; }
        .ports td { padding: 3px 8px 3px 0; border-bottom: 1px solid #f3f4f6; color: #111827; }

        /* Rack-Frontansicht: Tabelle, weil DomPDF kein Grid kann */
        .rack-block { page-break-inside: avoid; margin: 0 0 6mm 10px; }
        .rack-caption { font-weight: 600; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.9px; color: #9ca3af; margin-bottom: 4px; }
        .rackview { border-collapse: collapse; border: 1px solid #9ca3af; background: #e5e7eb; }
        /* vertical-align/display: sonst sitzt das Bild auf der Textbasislinie
           und die Zeile wird ein paar Pixel höher als die Zeichnung */
        .rackview td { padding: 0; vertical-align: top; }
        .rackview img { display: block; }
        .rackview-scale {
            width: 20px; height: 16px; text-align: right; padding-right: 4px !important;
            font-size: 6px; color: #9ca3af; background: #e5e7eb;
        }
        .rackview-slot { width: 330px; height: 16px; }
        .rackview-empty { width: 330px; height: 16px; background: #ffffff; }
    </style>
</head>

<body>

    {{-- Deckblatt --}}
    <div class="cover">
        <div class="cover-app">{{ \App\Models\Setting::appName() }}</div>
        <div class="cover-rule"></div>
        <div class="cover-eyebrow">{{ __('Dokumentation') }}</div>
        <div class="cover-customer">{{ $customer->name }}</div>
        <div class="cover-date">{{ __('Stand') }}: {{ \App\Support\LocalTime::display(now(), 'd.m.Y') }}</div>

        {{-- Das Blatt traegt Kennwoerter, BMC-Zugaenge und WLAN-Schluessel.
             Wer es ausgedruckt liegen laesst, gibt das Netz her. --}}
        <div class="cover-note">
            {{ __('Dieses Dokument enthält Zugangsdaten und Kennwörter. Bitte entsprechend aufbewahren, weitergeben und vernichten.') }}
        </div>
    </div>

    {{-- Inhalt. Die Seitenzahlen stammen aus einem vorhergehenden
         Rendervorgang (siehe GenerateCustomerPdf); beim ersten Durchlauf ist
         $toc leer und die Seite entfaellt ganz. --}}
    @if (! empty($toc))
        <div class="toc">
            <div class="toc-title">{{ __('Inhalt') }}</div>
            {{-- Zwei Spalten: 57 Zeilen liefen sonst auf eine dritte Seite,
                 auf der zwei Eintraege standen. Geteilt wird vor einem
                 Kapitel, damit keine Abschnitte von ihrer Ueberschrift
                 getrennt werden. --}}
            @php
                $mitte = (int) ceil(count($toc) / 2);
                $trenn = count($toc);

                foreach ($toc as $i => $eintrag) {
                    if ($i >= $mitte && $eintrag['kind'] === 'chapter') {
                        $trenn = $i;
                        break;
                    }
                }

                $spalten = [array_slice($toc, 0, $trenn), array_slice($toc, $trenn)];
            @endphp
            <table class="toc-columns">
                <tr>
                    @foreach ($spalten as $spalte)
                        @if (! $loop->first)
                            <td class="toc-gap"></td>
                        @endif
                        <td class="toc-column">
                            <table>
                                @foreach ($spalte as $eintrag)
                                    <tr class="toc-{{ $eintrag['kind'] }}">
                                        <td class="toc-name">{{ $eintrag['title'] }}</td>
                                        <td class="toc-page">{{ $eintrag['page'] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    @php
        // The parts drawn outside x-pdf.section check the same right.
        $mayView = fn (string $class) => ($pdfOwner ?? null) && \Illuminate\Support\Facades\Gate::forUser($pdfOwner)->allows('viewAny', $class);
        $deviceTitle = fn ($x) => $x->name ?: (trim(($x->manufacturer ?? '') . ' ' . ($x->model ?? '')) ?: '#' . $x->id);
        $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';
    @endphp

    <x-pdf.chapter :title="__('Stammdaten')" first />

    <x-pdf.section :title="__('Standorte')" :items="$customer->sites" :groups="[
        'Adresse' => ['Straße' => fn($s) => trim(($s->street ?? '').' '.($s->house_number ?? '')), 'PLZ / Ort' => fn($s) => trim(($s->zip ?? '').' '.($s->city ?? ''))],
    ]" />

    <x-pdf.section :title="__('Ansprechpartner')" :items="$customer->contactpersons" :titleField="fn($c) => trim(($c->first_name ?? '').' '.($c->last_name ?? ''))" :groups="[
        'Kontakt' => ['Funktion' => 'role', 'Telefon' => 'phone', 'E-Mail' => 'mail'],
    ]" />

    <x-pdf.chapter :title="__('Netzwerk')" />

    <x-pdf.section :title="__('Internet / WAN')" :items="$customer->internetconnections" :titleField="fn($i) => trim(($i->provider ?? '').' '.($i->product ? '– '.$i->product : ''))" :groups="[
        'Vertrag' => ['Anbieter' => 'provider', 'Produkt' => 'product', 'Vertragsnummer' => 'contract_number', 'Anschlussart' => 'connection_type'],
        'Technik' => ['Download' => fn($i) => $i->bandwidth($i->bandwidth_down), 'Upload' => fn($i) => $i->bandwidth($i->bandwidth_up), 'WAN-IP' => 'wan_ip', 'Hotline' => 'hotline'],
        'Einwahl (PPPoE)' => ['Benutzer' => 'pppoe_user', 'Passwort' => 'pppoe_password'],
        'Geroutetes Netz' => ['Netz' => 'subnet', 'Gateway' => 'subnet_gateway', 'Nutzbar' => fn($i) => $i->usableRange()],
    ]" />

    <x-pdf.section :title="__('Firewalls')" :items="$customer->firewalls" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Firmware' => 'firmware', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Zugang' => ['Oberfläche' => 'management_url', 'Port' => 'port'],
        'Subscription' => ['Läuft bis' => fn($f) => $date($f->subscription_until)],
        'Sonstiges' => ['Notizen' => 'notes'],
    ]" />

    <x-pdf.section :title="__('Router')" :items="$customer->routers" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port'],
    ]" />

    <x-pdf.section :title="__('Netzwerke (VLAN)')" :items="$customer->networks" :titleField="fn($n) => $n->description ?: $n->network ?: 'VLAN '.$n->vlanId" :groups="[
        'Netzwerk' => ['VLAN-ID' => 'vlanId', 'Netz' => 'network', 'Subnetzmaske' => 'subnetmask', 'Gateway' => 'gateway'],
        'DHCP' => ['Start' => 'dhcpStart', 'Ende' => 'dhcpEnd'],
        'DNS' => ['DNS 1' => 'dns1', 'DNS 2' => 'dns2'],
    ]" />

    <x-pdf.section :title="__('WLAN-Netze')" :items="$customer->wifis" titleField="ssid" :groups="[
        'WLAN' => ['SSID' => 'ssid', 'Passwort' => 'password', 'Verschlüsselung' => 'encryption'],
    ]" />

    <x-pdf.section :title="__('Switches')" :items="$customer->networkswitches" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Montageort' => 'location', 'Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port'],
    ]" />

    <x-pdf.section :title="__('Accesspoints')" :items="$customer->accesspoints" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Montageort' => 'location', 'Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port'],
    ]" />

    @php $patchpanels = $customer->patchpanels()->with('ports.networkSwitch')->get(); @endphp
    <x-pdf.section :title="__('Patchfelder')" :items="$patchpanels" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model',
            'Ports' => 'port_count', 'Höheneinheiten' => fn($p) => $p->height_units . ' HE', 'Notiz' => 'note'],
    ]" />

    {{-- Outlets per panel: only labelled ports, otherwise 48 empty rows. --}}
    @foreach ($mayView(\App\Models\PatchPanel::class) ? $patchpanels : [] as $panel)
        @php $belegt = $panel->ports->filter(fn ($p) => $p->isDocumented()); @endphp
        @if ($belegt->isNotEmpty())
            <div class="ports-block">
                <div class="ports-caption">{{ $panel->name }} – Dosenbelegung</div>
                <table class="ports">
                    <tr>
                        <th>{{ __('Port') }}</th><th>{{ __('Dose') }}</th><th>{{ __('Raum') }}</th><th>{{ __('Switch') }}</th><th>{{ __('Switch-Port') }}</th><th>{{ __('Notiz') }}</th>
                    </tr>
                    @foreach ($belegt as $port)
                        <tr>
                            <td>{{ $port->number }}</td>
                            <td>{{ $port->outlet }}</td>
                            <td>{{ $port->label }}</td>
                            <td>{{ $port->networkSwitch?->name }}</td>
                            <td>{{ $port->switch_port }}</td>
                            <td>{{ $port->note }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @endforeach

    @php $racks = $customer->racks()->with('items.device', 'items.catalogItem')->get(); @endphp
    <x-pdf.section :title="__('Serverschränke')" :items="$racks" :groups="[
        'Allgemein' => ['Ort' => 'location', 'Höheneinheiten' => fn($r) => $r->height_units . ' HE',
            'Einbauten' => fn($r) => $r->items->count(), 'Notiz' => 'note'],
    ]" />

    {{-- Die Belegung steht als Zeichnung statt als Aufzählung: im Schrank vor
         Ort sucht man nach dem Bild, nicht nach einer Liste. --}}
    @foreach ($mayView(\App\Models\Rack::class) ? $racks : [] as $rack)
        <div class="rack-block">
            <div class="rack-caption">{{ $rack->name }} – {{ __('Frontansicht') }}</div>
            @include('pdf._rack', ['rack' => $rack, 'svgDir' => $svgDir, 'seite' => 'front'])
        </div>

        {{-- Rueckansicht nur, wenn dort etwas eingebaut ist - eine leere
             Zeichnung kostet im Ausdruck eine halbe Seite und sagt nichts. --}}
        @if ($rack->items->where('side', 'rear')->isNotEmpty())
            <div class="rack-block">
                <div class="rack-caption">{{ $rack->name }} – {{ __('Rückansicht') }}</div>
                @include('pdf._rack', ['rack' => $rack, 'svgDir' => $svgDir, 'seite' => 'rear'])
            </div>
        @endif
    @endforeach

    <x-pdf.chapter :title="__('Server & Storage')" />

    <x-pdf.section :title="__('Cluster')" :items="$customer->clusters()->with('servers')->get()" :groups="[
        'Cluster' => ['Art' => fn($c) => $c->typeLabel(), 'Knoten' => fn($c) => $c->servers->pluck('name')->implode(', '), 'Notiz' => 'note'],
    ]" />

    <x-pdf.section :title="__('Server')" :items="$customer->servers" :groups="[
        'Hardware' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Bauform' => fn($s) => __(config('custom.server_form_factors')[$s->form_factor] ?? ''), 'Einbautiefe' => fn($s) => $s->form_factor === 'rack' ? __(config('custom.server_depths')[(int) $s->full_depth] ?? '') : null, 'Höheneinheiten' => fn($s) => $s->form_factor === 'rack' ? $s->height_units.' HE' : null, 'Betriebssystem' => fn($s) => $s->operatingSystem?->name, 'Cluster' => fn($s) => $s->cluster?->name, 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'BMC' => ['IP' => 'bmcIp', 'Benutzer' => 'bmcUser', 'Passwort' => 'bmcPassword'],
    ]" />

    <x-pdf.section :title="__('VMs')" :items="$customer->vms" :groups="[
        'Allgemein' => ['Host' => fn($v) => $v->host?->name],
        'Betriebssystem' => ['OS' => fn($v) => $v->operatingSystem?->name],
    ]" />

    <x-pdf.section :title="__('NAS')" :items="$customer->nas" :groups="[
        'Hardware' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port'],
    ]" />

    <x-pdf.chapter :title="__('Clients')" />

    <x-pdf.section :title="__('Computer')" :items="$customer->computers" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Betriebssystem' => ['OS' => fn($c) => $c->operatingSystem?->name],
    ]" />

    <x-pdf.section :title="__('Drucker')" :items="$customer->printers" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port'],
    ]" />

    <x-pdf.section :title="__('Scanner')" :items="$customer->scanners" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Scan-Ziele' => ['Ziele' => fn($s) => $s->scanTargets->map(fn($z) => $z->name ?: $z->target)->implode(', ')],
    ]" />

    <x-pdf.section :title="__('Scan-Ziele')" :items="$customer->scantargets()->with('scanners', 'login')->get()" :titleField="fn($z) => $z->name ?: $z->target" :groups="[
        'Ziel' => ['Art' => fn($z) => $z->typeName(), 'Ziel' => 'target', 'Beschreibung' => 'description'],
        'Zugang' => ['Zugangsdaten' => fn($z) => $z->login ? trim($z->login->name.($z->login->username ? ' ('.$z->login->username.')' : '')) : null],
        'Eingerichtet auf' => ['Scanner' => fn($z) => $z->scanners->pluck('name')->implode(', ')],
    ]" />

    <x-pdf.section :title="__('IoT-Geräte')" :items="$customer->iotdevices" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Montageort' => 'location', 'Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port', 'URL' => 'url'],
    ]" />

    <x-pdf.section :title="__('Maschinen')" :items="$customer->machines" :groups="[
        'Allgemein' => ['Standort' => fn($m) => $m->site?->name],
    ]" />

    <x-pdf.section :title="__('Sonstige Clients')" :items="$customer->otherclients" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port'],
    ]" />

    <x-pdf.chapter :title="__('Active Directory')" />

    <x-pdf.section :title="__('AD-Domänen')" :items="$customer->addomains" titleField="domain" :groups="[
        'Domäne' => ['Domäne' => 'domain', 'NetBIOS' => 'netbios', 'UPN-Suffixe' => 'upn_suffixes', 'Funktionsebene' => fn($d) => $d->functionalLevelLabel()],
        'Domänencontroller' => ['Domänencontroller' => fn($d) => $d->hostNames('dc'), 'FSMO-Rollen' => 'fsmo_holder', 'DSRM-Passwort' => 'dsrmpassword', 'DNS-Weiterleitungen' => 'dns_forwarders'],
        'Dienste' => ['DHCP-Server' => 'dhcp_server', 'Zertifizierungsstelle' => fn($d) => $d->hostNames('ca'), 'Entra Connect' => fn($d) => $d->entra_connect === null ? null : ($d->entra_connect ? 'Ja' : 'Nein'), 'Entra-Connect-Server' => fn($d) => $d->hostNames('entra_connect')],
        'Sonstiges' => ['Notizen' => 'notes'],
    ]" />

    <x-pdf.section :title="__('AD-Benutzer')" :items="$customer->adusers" :titleField="fn($u) => trim(($u->firstName ?? '').' '.($u->lastName ?? ''))" :groups="[
        'Allgemein' => ['E-Mail' => 'email', 'Status' => fn($u) => $u->enabled === null ? '—' : ($u->enabled ? 'Aktiv' : 'Deaktiviert')],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    <x-pdf.section :title="__('AD-Gruppen')" :items="$customer->adgroups" :groups="[
        'Gruppe' => ['Name' => 'name', 'Beschreibung' => 'description'],
    ]" />

    <x-pdf.chapter :title="__('Telefonie')" />

    <x-pdf.section :title="__('Telefonanlagen')" :items="$customer->phonesystems" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port'],
    ]" />

    <x-pdf.section :title="__('SIP-Anschlüsse')" :items="$customer->sipaccounts()->with('phoneSystem', 'internetConnection')->get()" titleField="provider" :groups="[
        'Anschluss' => ['Anschlussart' => fn($s) => $s->accountTypeLabel(), 'Produkt' => 'product', 'Vertragsnummer' => 'contract_number', 'Kundennummer' => 'provider_customer_number', 'Hotline' => 'hotline', 'Sprachkanäle' => 'channels'],
        'Rufnummern' => ['Stammnummer' => 'main_number', 'Durchwahlbereich' => 'number_range', 'Rufnummern' => 'numbers'],
        'Technik' => ['SIP-Registrar' => 'registrar', 'TK-Anlage' => fn($s) => $s->phoneSystemLabel(), 'Internetanschluss' => fn($s) => $s->internetConnectionLabel(), 'Notizen' => 'notes'],
    ]" />

    <x-pdf.section :title="__('Telefone')" :items="$customer->phones" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Durchwahl' => 'extension', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['MAC' => 'mac'],
    ]" />

    <x-pdf.section :title="__('DECT')" :items="$customer->dects" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Montageort' => 'location', 'Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['MAC' => 'mac'],
    ]" />

    <x-pdf.chapter :title="__('E-Mail')" />

    <x-pdf.section :title="__('E-Mail-Archivierung')" :items="$customer->securepointumas" :titleField="fn($u) => $u->name ?: ($u->manufacturer ?: 'E-Mail-Archivierung #'.$u->id)" :groups="[
        'Allgemein' => ['Hersteller / Produkt' => 'manufacturer', 'Art' => 'type'],
        'Verschlüsselung' => ['Verschlüsselungscode' => 'encryptionkey'],
        'URL' => ['Admin URL' => 'urlAdmin', 'User URL' => 'urlUser'],
    ]" />

    <x-pdf.section :title="__('E-Mail Postfächer')" :items="$customer->mailboxes" titleField="mailAdress" :groups="[
        'Login' => ['E-Mail' => 'mailAdress', 'Benutzer' => 'username', 'Passwort' => 'password'],
        'Server' => ['POP3' => fn($m) => $m->mailboxProvider?->pop3server, 'IMAP' => fn($m) => $m->mailboxProvider?->imapserver, 'SMTP' => fn($m) => $m->mailboxProvider?->smtpserver],
    ]" />

    <x-pdf.chapter :title="__('Kamera / Funk')" />

    <x-pdf.section :title="__('Recorder')" :items="$customer->recorders" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port'],
    ]" />

    <x-pdf.section :title="__('Kameras')" :items="$customer->cameras" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Montageort' => 'location', 'Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Netzwerk' => ['Port' => 'port'],
    ]" />


    <x-pdf.chapter :title="__('Logins')" />

    <x-pdf.section :title="__('Logins – Allgemein')" :items="$customer->logingenerals" :groups="[
        'Login' => ['Beschreibung' => 'description', 'Benutzer' => 'username', 'Passwort' => 'password'],
        'Verwendung' => ['Verwendet bei' => fn ($l) => $l->verwendetBei() ?: '—'],
    ]" />

    {{-- Der private Schluessel steht bewusst nicht im PDF: Er waere in einem
         weitergereichten Dokument nicht mehr einzufangen. --}}
    <x-pdf.section :title="__('SSH-Schlüssel')" :items="$customer->sshkeys" :groups="[
        'Schlüssel' => ['Benutzer' => 'username', 'Verfahren' => fn ($k) => $k->algorithmName(), 'Fingerprint' => 'fingerprint', 'Beschreibung' => 'description'],
        'Verwendung' => ['Verwendet bei' => fn ($k) => $k->verwendetBei() ?: '—'],
    ]" />

    <x-pdf.section :title="__('Logins – Webseiten')" :items="$customer->loginwebsites" :groups="[
        'Login' => ['URL' => 'url', 'Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    <x-pdf.chapter :title="__('Lizenzen')" />

    <x-pdf.section :title="__('Lizenzen – Windows')" :items="$customer->licensewindows" :titleField="fn($l) => $l->operatingSystem?->name ?: 'Windows-Lizenz #'.$l->id" :groups="[
        'Lizenz' => ['Lizenzschlüssel' => 'key'],
    ]" />

    <x-pdf.section :title="__('Lizenzen – Software')" :items="$customer->licensesoftware" :groups="[
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
        'Laufzeit' => ['Start' => fn($l) => $date($l->start_date), 'Ende' => fn($l) => $date($l->end_date), 'Abrechnung' => 'abo'],
        'Lizenzschlüssel' => ['Lizenzschlüssel' => 'key'],
    ]" />

    <x-pdf.section :title="__('Lizenzen – CAL')" :items="$customer->licenseaccesses" :groups="[
        'Lizenz' => ['Lizenzschlüssel' => 'key'],
    ]" />

    <x-pdf.chapter :title="__('Dienste')" />

    {{-- Die Zugaenge stehen im Abschnitt "Logins - Allgemein" mit dem Server
         unter "Verwendet bei" - wie bei jedem anderen Geraet auch. --}}
    <x-pdf.section :title="__('FTP-Server')" :items="$customer->ftpservers" :titleField="$deviceTitle" :groups="[
        'Server' => ['Host' => 'host', 'Beschreibung' => 'description'],
    ]" />

    <x-pdf.section :title="__('DynDNS')" :items="$customer->dyndns" :groups="[
        'Login' => ['Anbieter' => 'provider', 'Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    <x-pdf.section :title="__('Domains')" :items="$customer->domains" :groups="[
        'Allgemein' => ['Registrar' => 'registrar', 'Ablaufdatum' => fn($d) => $date($d->expiry_date)],
        'Nameserver' => ['NS 1' => 'nameserver1', 'NS 2' => 'nameserver2'],
    ]" />

    <x-pdf.section :title="__('Zertifikate')" :items="$customer->certificates" :groups="[
        'Allgemein' => ['Domain / CN' => 'common_name', 'Aussteller' => 'issuer', 'Typ' => 'type'],
        'Gültigkeit' => ['Ausgestellt am' => fn($c) => $date($c->issued_date), 'Ablaufdatum' => fn($c) => $date($c->expiry_date)],
    ]" />

    <x-pdf.chapter :title="__('Sonstiges')" />

    <x-pdf.section :title="__('USV')" :items="$customer->ups" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Kaufdatum' => fn($g) => $g->purchase_date?->format('d.m.Y'), 'Garantie bis' => fn($g) => $g->warranty_until?->format('d.m.Y'), 'Support-Ende' => fn($g) => $g->eol_date?->format('d.m.Y'), 'Lieferant' => 'supplier'],
        'Technik' => ['Kapazität' => 'capacity', 'Laufzeit' => 'runtime'],
    ]" />

    <x-pdf.section :title="__('Backup')" :items="$customer->backups" :groups="[
        'Konfiguration' => ['Software' => 'software', 'Quelle' => 'source', 'Ziel' => 'destination'],
        'Zeitplan' => ['Zeitplan' => 'schedule', 'Aufbewahrung' => 'retention', 'Letzter Erfolg' => fn($b) => $date($b->last_success)],
        'Login' => ['Passwort' => 'password'],
    ]" />

</body>

</html>
