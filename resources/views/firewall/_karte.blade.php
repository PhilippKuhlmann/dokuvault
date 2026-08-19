{{-- Eine Firewall in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="firewall_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'firewall', id: {{ $eintrag->id }} })">
                    {{ $eintrag->name }}

                    {{-- Was man an einer Firewall fast immer sucht: die Adresse,
                         der Einbauort und ob die Subscription noch laeuft. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($eintrag->firmware)
                            <x-kernwert :label="__('Firmware')">{{ $eintrag->firmware }}</x-kernwert>
                        @endif

                        @if ($eintrag->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $eintrag->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-ipcard :device="$eintrag" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $eintrag->manufacturer,
                    'Modell' => $eintrag->model,
                    'Seriennummer' => $eintrag->serialNumber,
                    'Firmware' => $eintrag->firmware,
                    'Bauform' => __(config('custom.firewall_form_factors')[$eintrag->form_factor] ?? ''),
                ]" />

                <x-credentialscard :device="$eintrag" />

                <x-minitablecard :title="__('Zugang')" :array="[
                    'Oberfläche' => $eintrag->management_url,
                    'Benutzername' => $eintrag->username,
                    'Passwort' => $eintrag->password,
                    'Port' => $eintrag->port,
                ]" />

                {{-- Die Karte blendet sich selbst aus, wenn nichts gefuellt ist -
                     bei einer Sophos bleiben diese vier Felder leer. Die
                     Beschriftungen sind so gewaehlt, dass minitablecard die
                     Geheimnisse maskiert. --}}
                <x-minitablecard :title="__('Securepoint')" :array="[
                    'USC-PIN' => $eintrag->usc_pin,
                    'Cloud Backup Passwort' => $eintrag->cloud_backup_password,
                    'User URL' => $eintrag->url_user,
                    'Externe URL' => $eintrag->url_external,
                ]" />

                <x-minitablecard :title="__('Subscription')" :array="[
                    'Läuft bis' => $eintrag->subscription_until?->format('d.m.Y'),
                ]" />

                <x-minitablecard :title="__('Notizen')" :array="[
                    'Notizen' => $eintrag->notes,
                ]" />

                <x-beschaffungcard :device="$eintrag" />

            </x-slot>
        </x-card>
    
