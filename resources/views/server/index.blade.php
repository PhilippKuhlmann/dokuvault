<x-app-layout :$customer>

    <x-sitetopmenu can="server_create" />


    @forelse ($servers as $server)
        <x-card>
            @php
                $adressen = $server->relationLoaded('ipAddresses') ? $server->ipAddresses : $server->ipAddresses()->get();
                $anzahlIps = $adressen->count();
                $primaer = $adressen->first()?->address;
            @endphp

            <x-slot:head>
                <x-show.header can="server_update" editUrl="{{ route('server.edit', [$customer, $server]) }}">
                    {{-- Die Fernwartung bleibt der erste Knopf in der Kopfzeile:
                         taeglich benutzt. Welches Werkzeug dahinter steckt,
                         steht in den Einstellungen. --}}
                    <x-remote.button :device="$server" />
                    {{ $server->name }}

                    {{-- Betriebssystem klein hinter den Namen: Es gehoert zur
                         Identitaet der Maschine, nicht zu den Nachschlagewerten. --}}
                    @if ($server->operatingSystem)
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ $server->operatingSystem->name }}</span>

                        <x-eol :os="$server->operatingSystem" />
                    @endif

                    {{-- Was man fast immer sucht, steht neben dem Namen statt irgendwo
                         in der Karte. Der Zaehler verraet schon hier, dass mehr da ist. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($server->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $server->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-ipcard :device="$server" />

                <x-credentialscard :device="$server" />

                <x-minitablecard :title="__('BMC')" :array="[
                    'BMC IP-Adresse' => $server->bmcIp,
                    'BMC Benutzer' => $server->bmcUser,
                    'BMC Passwort' => $server->bmcPassword,
                ]" />

                {{-- Die Fernwartungs-Kennung auch als Text: Wenn der Knopf nicht greift
                     (anderer Rechner, kein Client), braucht man die ID zum Abtippen. --}}
                <x-minitablecard :title="__('Fernwartung')" :array="[
                    \App\Models\Setting::fernwartung()['id_label'] => $server->remoteID,
                    'Passwort' => $server->remotePassword,
                ]" />

                <x-minitablecard :title="__('Hardware')" :array="[
                    'Hersteller' => $server->manufacturer,
                    'Modell' => $server->model,
                    'Seriennummer' => $server->serialNumber,
                    'Bauform' => __(config('custom.server_form_factors')[$server->form_factor] ?? ''),
                    'Einbautiefe' => $server->form_factor === 'rack'
                        ? __(config('custom.server_depths')[(int) $server->full_depth] ?? '')
                        : null,
                    'Höheneinheiten' => $server->form_factor === 'rack' ? $server->height_units.' HE' : null,
                ]" />

                {{-- Betriebssystem steht jetzt in der Kopfzeile - hier waere es doppelt. --}}
                <x-minitagcard :title="__('Dienste')" :array="$server->services" />

                <x-beschaffungcard :device="$server" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">
        {{ $servers->links() }}
    </div>

</x-app-layout>
