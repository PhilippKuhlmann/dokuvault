@props(['customer' => null])

{{-- Befehls-/Suchpalette: Cmd/Strg+K (oder "/") öffnet ein Feld, in dem man
     Seiten per Tippen findet und mit Enter dorthin springt.

     Die Ziele werden hier serverseitig gesammelt und nach Rechten gefiltert -
     so taucht in der Palette nur auf, was der Nutzer auch sehen darf, und die
     Liste steht ohne Server-Roundtrip sofort zum Filtern bereit. Route::has
     schützt vor einem Tippfehler im Routennamen (sonst 500 auf jeder Seite).

     Kundengebundene Ziele brauchen einen Kunden; auf den Admin-Seiten fehlt
     der, dort bleiben nur die globalen und die Admin-Ziele. --}}
@php
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Facades\Route as RouteFacade;

    $ziele = [];

    // Label kommt fertig übersetzt herein (aus __()-Teilen zusammengesetzt),
    // damit es im Englischen mitübersetzt - ein fester deutscher Schlüssel wie
    // "Admin · Rack-Katalog" hätte keine Übersetzung und bliebe deutsch.
    $sammeln = function (array $eintraege, ...$parameter) use (&$ziele) {
        foreach ($eintraege as [$recht, $label, $route]) {
            if (($recht === null || Gate::allows($recht)) && RouteFacade::has($route)) {
                $ziele[] = ['label' => $label, 'url' => route($route, $parameter)];
            }
        }
    };

    if ($customer) {
        $sammeln([
            [null, __('Dashboard'), 'customer.dashboard'],
            ['site_viewAny', __('Standort'), 'site.index'],
            ['contactperson_viewAny', __('Ansprechpartner'), 'contactperson.index'],
            ['internetconnection_viewAny', __('Internet / WAN'), 'internetconnection.index'],
            ['firewall_viewAny', __('Firewall'), 'firewall.index'],
            ['router_viewAny', __('Router'), 'router.index'],
            ['network_viewAny', __('VLAN'), 'network.index'],
            ['network_viewAny', __('IPAM'), 'ipplan.index'],
            ['wifi_viewAny', __('WLAN Netze'), 'wifi.index'],
            ['networkswitch_viewAny', __('Switch'), 'networkswitch.index'],
            ['accesspoint_viewAny', __('Accesspoint'), 'accesspoint.index'],
            ['rack_viewAny', __('Serverschränke'), 'rack.index'],
            ['patchpanel_viewAny', __('Patchfelder'), 'patchpanel.index'],
            ['server_viewAny', __('Server'), 'server.index'],
            ['cluster_viewAny', __('Cluster'), 'cluster.index'],
            ['vm_viewAny', __('VMs'), 'vm.index'],
            ['nas_viewAny', __('NAS'), 'nas.index'],
            ['computer_viewAny', __('Computer'), 'computer.index'],
            ['printer_viewAny', __('Drucker'), 'printer.index'],
            ['iotdevice_viewAny', __('IoT-Gerät'), 'iotdevice.index'],
            ['machine_viewAny', __('Maschinen'), 'machine.index'],
            ['otherclient_viewAny', __('Sonstige'), 'otherclient.index'],
            ['addomain_viewAny', __('AD-Domäne'), 'addomain.index'],
            ['aduser_viewAny', __('AD-User'), 'aduser.index'],
            ['adgroup_viewAny', __('AD-Gruppen'), 'adgroup.index'],
            ['phonesystem_viewAny', __('TK-Anlage'), 'phonesystem.index'],
            ['phone_viewAny', __('Telefon'), 'phone.index'],
            ['dect_viewAny', __('DECT'), 'dect.index'],
            ['logingeneral_viewAny', __('Logins').' · '.__('Allgemein'), 'logingeneral.index'],
            ['loginwebsite_viewAny', __('Logins').' · '.__('Webseiten'), 'loginwebsite.index'],
            ['sshkey_viewAny', __('SSH-Schlüssel'), 'sshkey.index'],
            ['securepointuma_viewAny', __('E-Mail-Archivierung'), 'securepointuma.index'],
            ['mailbox_viewAny', __('E-Mail Postfächer'), 'mailbox.index'],
            ['recorder_viewAny', __('Recorder'), 'recorder.index'],
            ['camera_viewAny', __('Kamera'), 'camera.index'],
            ['licensewindows_viewAny', __('Lizenzen').' · '.__('Windows'), 'licensewindows.index'],
            ['licenseaccess_viewAny', __('Lizenzen').' · '.__('CAL'), 'licenseaccess.index'],
            ['licensesoftware_viewAny', __('Lizenzen').' · '.__('Software'), 'licensesoftware.index'],
            ['ftpserver_viewAny', __('FTP-Server'), 'ftpserver.index'],
            ['dyndns_viewAny', __('DynDNS'), 'dyndns.index'],
            ['domain_viewAny', __('Domains'), 'domain.index'],
            ['certificate_viewAny', __('Zertifikate'), 'certificate.index'],
            ['backup_viewAny', __('Backup'), 'backup.index'],
            ['ups_viewAny', __('USV'), 'ups.index'],
            ['file_viewAny', __('Dateien'), 'file.index'],
            ['see_hidden', __('Auto-Dokumentation'), 'agent.index'],
            [null, __('Papierkorb'), 'trash.index'],
        ], $customer);
    }

    if (Gate::denies('isCustomer')) {
        $sammeln([
            [null, __('Kundensuche'), 'customer.search'],
            [null, __('Globale Suche'), 'search.global'],
            ['remote_search', __('Rustdesk Suche'), 'search.remote'],
        ]);
    }

    if (Gate::allows('admin_bereich')) {
        $adminPraefix = __('Admin').' · ';
        $sammeln([
            [null, $adminPraefix.__('Dashboard'), 'admin.dashboard'],
            ['admin_user', $adminPraefix.__('Benutzer'), 'admin.user.index'],
            ['admin_role', $adminPraefix.__('Rollen'), 'admin.role.index'],
            ['admin_customer', $adminPraefix.__('Kunden'), 'admin.customer.index'],
            ['admin_catalog', $adminPraefix.__('Betriebssysteme'), 'admin.operatingsystem.index'],
            ['admin_catalog', $adminPraefix.__('Mail Anbieter'), 'admin.mailboxprovider.index'],
            ['admin_catalog', $adminPraefix.__('Dienste'), 'admin.service.index'],
            ['admin_catalog', $adminPraefix.__('Rack-Katalog'), 'admin.rackcatalogitem.index'],
            ['admin_catalog', $adminPraefix.__('Gerätemodelle'), 'admin.devicemodel.index'],
            ['admin_operatingsystem', $adminPraefix.__('Support-Ende (EOL)'), 'admin.eol.index'],
            ['admin_setting', $adminPraefix.__('Allgemein'), 'admin.general.index'],
            ['admin_setting', $adminPraefix.__('Fernwartung'), 'admin.setting.index'],
            ['admin_setting', $adminPraefix.__('Mail'), 'admin.mail.index'],
            ['admin_setting', $adminPraefix.__('Sicherheit'), 'admin.security.index'],
            ['admin_setting', $adminPraefix.__('Fristen'), 'admin.fristen.index'],
            ['admin_activity', $adminPraefix.__('Protokoll-Historie'), 'admin.logretention'],
            ['admin_apitoken', $adminPraefix.__('API-Token'), 'admin.apitoken'],
            ['admin_trash', $adminPraefix.__('Alle Kunden'), 'admin.trash'],
            ['admin_activity', $adminPraefix.__('Aktivitäten'), 'admin.activity.index'],
        ]);
    }
@endphp

<div
    x-data="{
        offen: false,
        suche: '',
        aktiv: 0,
        ziele: @js($ziele),
        gefiltert() {
            const q = this.suche.trim().toLowerCase();
            const liste = q ? this.ziele.filter(z => z.label.toLowerCase().includes(q)) : this.ziele;
            if (this.aktiv >= liste.length) this.aktiv = Math.max(0, liste.length - 1);
            return liste;
        },
        tippt(e) {
            const t = e.target;
            return t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);
        },
        oeffnen() {
            this.offen = true;
            this.suche = '';
            this.aktiv = 0;
            this.$nextTick(() => this.$refs.feld && this.$refs.feld.focus());
        },
        schliessen() { this.offen = false; },
        runter() { const n = this.gefiltert().length; if (n) this.aktiv = (this.aktiv + 1) % n; },
        hoch() { const n = this.gefiltert().length; if (n) this.aktiv = (this.aktiv - 1 + n) % n; },
        waehlen() { const z = this.gefiltert()[this.aktiv]; if (z) window.location = z.url; },
        taste(e) {
            // "/" oeffnet nicht mehr direkt die Palette - das uebernimmt die
            // Komponente x-keyboard und springt zuerst in die Suche der Seite;
            // nur ohne Suchfeld faellt es per palette-open-Event hierher zurueck.
            // Cmd/Strg+K bleibt.
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.oeffnen(); return; }
        },
    }"
    x-on:keydown.window="taste($event)"
    x-on:keydown.escape.window="schliessen()"
    x-on:palette-open.window="oeffnen()"
>
    <template x-if="offen">
        <div class="fixed inset-0 z-[60] flex items-start justify-center px-4 pt-24">
            {{-- Verdunkelung; Klick daneben schließt. --}}
            <div class="fixed inset-0 bg-black/40" x-on:click="schliessen()"></div>

            <div class="relative w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                {{-- type="search" und die ignore-Attribute halten Passwortmanager
                     (1Password, LastPass, Bitwarden) vom Suchfeld fern - sonst
                     hielten sie es fuer ein Login-Feld und blendeten ihr Menue ein. --}}
                <input x-ref="feld" type="search" x-model="suche" autocomplete="off"
                    data-1p-ignore data-lpignore="true" data-bwignore data-form-type="other"
                    x-on:input="aktiv = 0"
                    x-on:keydown.arrow-down.prevent="runter()"
                    x-on:keydown.arrow-up.prevent="hoch()"
                    x-on:keydown.enter.prevent="waehlen()"
                    placeholder="{{ __('Seite suchen … (z. B. „Server“)') }}"
                    class="w-full border-0 border-b border-gray-200 bg-transparent px-4 py-3 text-sm text-gray-900 focus:ring-0 dark:border-gray-700 dark:text-gray-100">

                <ul class="max-h-80 overflow-y-auto py-1">
                    <template x-for="(z, i) in gefiltert()" :key="z.url">
                        <li>
                            <a :href="z.url"
                                x-on:mouseenter="aktiv = i"
                                :class="i === aktiv ? 'bg-cerulean-50 text-cerulean-800 dark:bg-gray-700 dark:text-cerulean-300' : 'text-gray-700 dark:text-gray-200'"
                                class="block px-4 py-2 text-sm">
                                <span x-text="z.label"></span>
                            </a>
                        </li>
                    </template>
                    <template x-if="gefiltert().length === 0">
                        <li class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Nichts gefunden') }}</li>
                    </template>
                </ul>

                <div class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-400 dark:border-gray-700 dark:text-gray-500">
                    {{ __('↑↓ wählen · ↵ öffnen · Esc schließen') }}
                </div>
            </div>
        </div>
    </template>
</div>
