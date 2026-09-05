{{-- Ein Server in der Liste. Die Karte bleibt beim Typ. --}}
        <x-card>
            @php
                $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
                $anzahlIps = $adressen->count();
                $primaer = $adressen->first()?->anzeige();
            @endphp

            <x-slot:head>
                <x-show.header can="server_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'server', id: {{ $eintrag->id }} })">
                    {{-- Die Fernwartung bleibt der erste Knopf in der Kopfzeile:
                         taeglich benutzt. Welches Werkzeug dahinter steckt,
                         steht in den Einstellungen. --}}
                    <x-remote.button :device="$eintrag" />
                    {{ $eintrag->name }}

                    {{-- Betriebssystem klein hinter den Namen: Es gehoert zur
                         Identitaet der Maschine, nicht zu den Nachschlagewerten. --}}
                    @if ($eintrag->operatingSystem)
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ $eintrag->operatingSystem->name }}</span>

                        <x-eol :os="$eintrag->operatingSystem" />
                    @endif

                    {{-- Was man fast immer sucht, steht neben dem Namen statt irgendwo
                         in der Karte. Der Zaehler verraet schon hier, dass mehr da ist. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-ip-anzeige :adresse="$adressen->first()" />
                            </x-kernwert>
                        @endif

                        @if ($eintrag->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $eintrag->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-ipcard :device="$eintrag" />

                <x-credentialscard :device="$eintrag" />

                <x-minitablecard :title="__('BMC')" :array="[
                    'BMC IP-Adresse' => $eintrag->bmcIp,
                    'BMC Benutzer' => $eintrag->bmcUser,
                    'BMC Passwort' => $eintrag->bmcPassword,
                ]" />

                {{-- Die Fernwartungs-Kennung auch als Text: Wenn der Knopf nicht greift
                     (anderer Rechner, kein Client), braucht man die ID zum Abtippen. --}}
                <x-minitablecard :title="__('Fernwartung')" :array="[
                    \App\Models\Setting::fernwartung()['id_label'] => $eintrag->remoteID,
                    'Passwort' => $eintrag->remotePassword,
                ]" />

                <x-minitablecard :title="__('Hardware')" :array="[
                    'Hersteller' => $eintrag->manufacturer,
                    'Modell' => $eintrag->model,
                    'Seriennummer' => $eintrag->serialNumber,
                    'Bauform' => __(config('custom.server_form_factors')[$eintrag->form_factor] ?? ''),
                    'Einbautiefe' => $eintrag->form_factor === 'rack'
                        ? __(config('custom.server_depths')[(int) $eintrag->full_depth] ?? '')
                        : null,
                    'Höheneinheiten' => $eintrag->form_factor === 'rack' ? $eintrag->height_units.' HE' : null,
                ]" />

                {{-- Betriebssystem steht jetzt in der Kopfzeile - hier waere es doppelt. --}}
                <x-minitagcard :title="__('Dienste')" :array="$eintrag->services" />

                <x-beschaffungcard :device="$eintrag" />

            </x-slot>
        </x-card>
    
