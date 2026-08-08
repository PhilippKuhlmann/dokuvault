<!DOCTYPE html>
<html lang="de">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Dokumentation - {{ $customer->name }}</title>

    <style>
        @font-face { font-family: 'CoconPro'; src: url('fonts/CoconPro.otf'); }
        @font-face { font-family: 'DINPro-Regular'; src: url('fonts/DINPro-Regular.otf'); }

        * { box-sizing: border-box; }
        html { font-family: 'DINPro-Regular'; color: #111827; font-size: 12px; }
        /* In DomPDF wird der body-Rand als Seitenrand auf JEDER Seite angewendet
           -> Druckränder für Drucker ohne Randlosdruck */
        body { margin: 10mm; }
        .CoconPro { font-family: 'CoconPro'; }

        /* Deckblatt */
        .cover { text-align: center; padding-top: 240px; }
        .cover-app { font-family: 'CoconPro'; font-size: 20px; color: #3391f0; margin-bottom: 14px; }
        .cover-bar { width: 110px; height: 5px; background: #3391f0; margin: 0 auto 26px; }
        .cover-title { font-family: 'CoconPro'; font-size: 54px; color: #1f3d6e; }
        .cover-customer { font-size: 26px; color: #6b7280; margin-top: 6px; }
        .cover-date { margin-top: 36px; font-size: 12px; color: #9ca3af; }

        .page-break { page-break-after: always; }

        /* Abschnitte: jeder Abschnitt beginnt auf einer neuen Seite
           -> maximal eine Überschrift pro Seite */
        .section { page-break-before: always; }
        .heading { font-family: 'CoconPro'; font-size: 23px; color: #1f3d6e; border-bottom: 2px solid #3391f0; padding-bottom: 4px; margin-bottom: 10px; }

        .card { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 10px; page-break-inside: avoid; }
        .card-title { font-family: 'CoconPro'; font-size: 15px; color: #1f3d6e; background: #f3f6fb; padding: 6px 10px; border-bottom: 1px solid #e5e7eb; border-radius: 6px 6px 0 0; }
        .card-body { padding: 8px 10px; }

        .card-table { float: left; margin-right: 3%; margin-bottom: 6px; }
        .card-table-title { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; margin-bottom: 3px; }
        .card-table table { width: 100%; border-collapse: collapse; }
        .card-table td { padding: 2px 0; font-size: 11px; vertical-align: top; }
        .card-table td.key { color: #6b7280; width: 45%; padding-right: 8px; }
        .card-table td.val { color: #111827; }

        .clear { clear: both; }
        .empty { color: #9ca3af; font-size: 12px; font-style: italic; padding: 2px 0 6px; }

        /* Dosenbelegung eines Patchfelds */
        .ports-block { page-break-inside: avoid; margin: 4px 0 10px; }
        .ports-caption { font-size: 11px; color: #6b7280; margin-bottom: 3px; }
        .ports { width: 100%; border-collapse: collapse; font-size: 10px; }
        .ports th { text-align: left; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 3px 6px 3px 0; font-weight: normal; }
        .ports td { padding: 3px 6px 3px 0; border-bottom: 1px solid #f3f4f6; color: #111827; }

        /* Rack-Frontansicht: Tabelle, weil DomPDF kein Grid kann */
        .rack-block { page-break-inside: avoid; margin: 4px 0 10px; }
        .rack-caption { font-size: 11px; color: #6b7280; margin-bottom: 3px; }
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
        <div class="cover-app">{{ config('app.name') }}</div>
        <div class="cover-bar"></div>
        <div class="cover-title">{{ __('Dokumentation') }}</div>
        <div class="cover-customer">{{ $customer->name }}</div>
        <div class="cover-date">Stand: {{ date('d.m.Y') }}</div>
    </div>

    @php
        $deviceTitle = fn ($x) => $x->name ?: (trim(($x->manufacturer ?? '') . ' ' . ($x->model ?? '')) ?: '#' . $x->id);
        $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';
    @endphp

    {{-- Stammdaten --}}
    <x-pdf.section :title="__('Standorte')" :items="$customer->sites" :groups="[
        'Adresse' => ['Straße' => fn($s) => trim(($s->street ?? '').' '.($s->house_number ?? '')), 'PLZ / Ort' => fn($s) => trim(($s->zip ?? '').' '.($s->city ?? ''))],
    ]" />

    <x-pdf.section :title="__('Ansprechpartner')" :items="$customer->contactpersons" :titleField="fn($c) => trim(($c->first_name ?? '').' '.($c->last_name ?? ''))" :groups="[
        'Kontakt' => ['Telefon' => 'phone', 'E-Mail' => 'mail'],
    ]" />

    {{-- Netzwerk --}}
    <x-pdf.section :title="__('Internet / WAN')" :items="$customer->internetconnections" :titleField="fn($i) => trim(($i->provider ?? '').' '.($i->product ? '– '.$i->product : ''))" :groups="[
        'Vertrag' => ['Anbieter' => 'provider', 'Produkt' => 'product', 'Vertragsnummer' => 'contract_number', 'Anschlussart' => 'connection_type'],
        'Technik' => ['Download' => 'bandwidth_down', 'Upload' => 'bandwidth_up', 'WAN-IP' => 'wan_ip', 'Hotline' => 'hotline'],
        'Geroutetes Netz' => ['Netz' => 'subnet', 'Gateway' => 'subnet_gateway', 'Nutzbar' => fn($i) => $i->nutzbarerBereich()],
    ]" />

    <x-pdf.section :title="__('Securepoint UTM')" :items="$customer->securepointutms" :titleField="fn($u) => $u->type ?: 'UTM #'.$u->id" :groups="[
        'Allgemein' => ['Art' => 'type', 'Seriennummer' => 'serialNumber'],
        'Login' => ['Benutzername' => 'username', 'Passwort' => 'password', 'Cloud Backup' => 'cloudBackupPassword', 'USC-PIN' => 'uscpin'],
        'URL' => ['IP' => 'ip', 'Admin URL' => 'urlAdmin', 'User URL' => 'urlUser', 'Externe URL' => 'urlExternal'],
    ]" />

    <x-pdf.section :title="__('Router')" :items="$customer->routers" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Login' => ['Benutzername' => 'username', 'Passwort' => 'password'],
        'Netzwerk' => ['IP' => 'ip', 'Port' => 'port'],
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
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Login' => ['Benutzername' => 'username', 'Passwort' => 'password'],
        'Netzwerk' => ['IP' => 'ip', 'Port' => 'port'],
    ]" />

    <x-pdf.section :title="__('Accesspoints')" :items="$customer->accesspoints" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Login' => ['Benutzername' => 'username', 'Passwort' => 'password'],
        'Netzwerk' => ['IP' => 'ip', 'Port' => 'port'],
    ]" />

    @php $patchpanels = $customer->patchpanels()->with('ports.networkSwitch')->get(); @endphp
    <x-pdf.section :title="__('Patchfelder')" :items="$patchpanels" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model',
            'Ports' => 'port_count', 'Höheneinheiten' => fn($p) => $p->height_units . ' HE', 'Notiz' => 'note'],
    ]" />

    {{-- Dosenbelegung je Feld: nur beschriftete Ports, sonst stehen hier 48 leere Zeilen. --}}
    @foreach ($patchpanels as $panel)
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

    @php $racks = $customer->racks()->with('items.device')->get(); @endphp
    <x-pdf.section :title="__('Serverschränke')" :items="$racks" :groups="[
        'Allgemein' => ['Ort' => 'location', 'Höheneinheiten' => fn($r) => $r->height_units . ' HE',
            'Einbauten' => fn($r) => $r->items->count(), 'Notiz' => 'note'],
    ]" />

    {{-- Die Belegung steht als Zeichnung statt als Aufzählung: im Schrank vor
         Ort sucht man nach dem Bild, nicht nach einer Liste. --}}
    @foreach ($racks as $rack)
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

    {{-- Server & Storage --}}
    <x-pdf.section :title="__('Server')" :items="$customer->servers" :groups="[
        'Hardware' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber', 'Bauform' => fn($s) => __(config('custom.server_form_factors')[$s->form_factor] ?? ''), 'Einbautiefe' => fn($s) => $s->form_factor === 'rack' ? __(config('custom.server_depths')[(int) $s->full_depth] ?? '') : null, 'Höheneinheiten' => fn($s) => $s->form_factor === 'rack' ? $s->height_units.' HE' : null, 'Betriebssystem' => fn($s) => $s->operatingSystem?->name],
        'Netzwerk' => ['IP 1' => 'ip1', 'IP 2' => 'ip2'],
        'BMC' => ['IP' => 'bmcIp', 'Benutzer' => 'bmcUser', 'Passwort' => 'bmcPassword'],
    ]" />

    <x-pdf.section :title="__('VMs')" :items="$customer->vms" :groups="[
        'Allgemein' => ['Host' => fn($v) => $v->host?->name],
        'Netzwerk' => ['IP 1' => 'ip1', 'IP 2' => 'ip2'],
        'Betriebssystem' => ['OS' => fn($v) => $v->operatingSystem?->name],
    ]" />

    <x-pdf.section :title="__('NAS')" :items="$customer->nas" :groups="[
        'Hardware' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Netzwerk' => ['IP 1' => 'ip1', 'IP 2' => 'ip2', 'Port' => 'port'],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    {{-- Clients --}}
    <x-pdf.section :title="__('Computer')" :items="$customer->computers" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Netzwerk' => ['IP-Adresse' => 'ip'],
        'Betriebssystem' => ['OS' => fn($c) => $c->operatingSystem?->name],
    ]" />

    <x-pdf.section :title="__('Drucker')" :items="$customer->printers" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Netzwerk' => ['IP' => 'ip', 'Port' => 'port'],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    <x-pdf.section :title="__('IoT-Geräte')" :items="$customer->iotdevices" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Netzwerk' => ['IP' => 'ip', 'Port' => 'port', 'URL' => 'url'],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    <x-pdf.section :title="__('Maschinen')" :items="$customer->machines" :groups="[
        'Allgemein' => ['IP-Adresse' => 'ip'],
    ]" />

    <x-pdf.section :title="__('Sonstige Clients')" :items="$customer->otherclients" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Netzwerk' => ['IP' => 'ip', 'Port' => 'port'],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    {{-- Active Directory --}}
    <x-pdf.section :title="__('AD-Domänen')" :items="$customer->addomains" titleField="domain" :groups="[
        'Domäne' => ['Domäne' => 'domain', 'NetBIOS' => 'netbios', 'DSRM-Passwort' => 'dsrmpassword'],
    ]" />

    <x-pdf.section :title="__('AD-Benutzer')" :items="$customer->adusers" :titleField="fn($u) => trim(($u->firstName ?? '').' '.($u->lastName ?? ''))" :groups="[
        'Allgemein' => ['E-Mail' => 'email', 'Status' => fn($u) => $u->enabled === null ? '—' : ($u->enabled ? 'Aktiv' : 'Deaktiviert')],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    <x-pdf.section :title="__('AD-Gruppen')" :items="$customer->adgroups" :groups="[
        'Gruppe' => ['Name' => 'name', 'Beschreibung' => 'description'],
    ]" />

    {{-- Telefonie --}}
    <x-pdf.section :title="__('Telefonanlagen')" :items="$customer->phonesystems" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Netzwerk' => ['IP 1' => 'ip1', 'Port' => 'port'],
    ]" />

    <x-pdf.section :title="__('Telefone')" :items="$customer->phones" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Durchwahl' => 'extension'],
        'Netzwerk' => ['IP' => 'ip', 'MAC' => 'mac'],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    <x-pdf.section :title="__('DECT')" :items="$customer->dects" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Netzwerk' => ['IP' => 'ip', 'MAC' => 'mac'],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    {{-- E-Mail --}}
    <x-pdf.section :title="__('E-Mail-Archivierung')" :items="$customer->securepointumas" :titleField="fn($u) => $u->name ?: ($u->manufacturer ?: 'E-Mail-Archivierung #'.$u->id)" :groups="[
        'Allgemein' => ['Hersteller / Produkt' => 'manufacturer', 'Art' => 'type'],
        'Login' => ['Benutzername' => 'username', 'Passwort' => 'password', 'Verschlüsselungscode' => 'encryptionkey'],
        'URL' => ['IP' => 'ip', 'Admin URL' => 'urlAdmin', 'User URL' => 'urlUser'],
    ]" />

    <x-pdf.section :title="__('E-Mail Postfächer')" :items="$customer->mailboxes" titleField="mailAdress" :groups="[
        'Login' => ['E-Mail' => 'mailAdress', 'Benutzer' => 'username', 'Passwort' => 'password'],
        'Server' => ['POP3' => fn($m) => $m->mailboxProvider?->pop3server, 'IMAP' => fn($m) => $m->mailboxProvider?->imapserver, 'SMTP' => fn($m) => $m->mailboxProvider?->smtpserver],
    ]" />

    {{-- Kamera / Funk --}}
    <x-pdf.section :title="__('Recorder')" :items="$customer->recorders" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Netzwerk' => ['IP' => 'ip', 'Port' => 'port'],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    <x-pdf.section :title="__('Kameras')" :items="$customer->cameras" :titleField="$deviceTitle" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Netzwerk' => ['IP' => 'ip', 'Port' => 'port'],
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />


    {{-- Logins --}}
    <x-pdf.section :title="__('Logins – Allgemein')" :items="$customer->logingenerals" :groups="[
        'Login' => ['Beschreibung' => 'description', 'Benutzer' => 'username', 'Passwort' => 'password'],
        'Verwendung' => ['Verwendet bei' => fn ($l) => $l->verwendetBei() ?: '—'],
    ]" />

    <x-pdf.section :title="__('Logins – Webseiten')" :items="$customer->loginwebsites" :groups="[
        'Login' => ['URL' => 'url', 'Benutzer' => 'username', 'Passwort' => 'password'],
    ]" />

    {{-- Lizenzen --}}
    <x-pdf.section :title="__('Lizenzen – Windows')" :items="$customer->licensewindows" :titleField="fn($l) => $l->operatingSystem?->name ?: 'Windows-Lizenz #'.$l->id" :groups="[
        'Lizenz' => ['Key' => 'key'],
    ]" />

    <x-pdf.section :title="__('Lizenzen – Software')" :items="$customer->licensesoftware" :groups="[
        'Login' => ['Benutzer' => 'username', 'Passwort' => 'password'],
        'Laufzeit' => ['Start' => fn($l) => $date($l->start_date), 'Ende' => fn($l) => $date($l->end_date), 'Abrechnung' => 'abo'],
        'Key' => ['Key' => 'key'],
    ]" />

    <x-pdf.section :title="__('Lizenzen – CAL')" :items="$customer->licenseaccesses" :groups="[
        'Lizenz' => ['Key' => 'key'],
    ]" />

    {{-- Dienste --}}
    <x-pdf.section :title="__('FTP-Server')" :items="$customer->ftpservers" :titleField="$deviceTitle" :groups="[
        'Login' => ['Host' => 'ip', 'Benutzer' => 'username', 'Passwort' => 'password'],
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

    {{-- Sonstiges --}}
    <x-pdf.section :title="__('USV')" :items="$customer->ups" :groups="[
        'Allgemein' => ['Hersteller' => 'manufacturer', 'Modell' => 'model', 'Seriennummer' => 'serialNumber'],
        'Technik' => ['IP' => 'ip', 'Kapazität' => 'capacity', 'Laufzeit' => 'runtime'],
    ]" />

    <x-pdf.section :title="__('Backup')" :items="$customer->backups" :groups="[
        'Konfiguration' => ['Software' => 'software', 'Quelle' => 'source', 'Ziel' => 'destination'],
        'Zeitplan' => ['Zeitplan' => 'schedule', 'Aufbewahrung' => 'retention', 'Letzter Erfolg' => fn($b) => $date($b->last_success)],
        'Login' => ['Passwort' => 'password'],
    ]" />

</body>

</html>
