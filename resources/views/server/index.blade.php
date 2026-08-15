<x-app-layout :$customer>

    <x-sitetopmenu can="server_create" />


    @forelse ($servers as $server)
        <x-card>
            @php
                $adressen = $server->relationLoaded('ipAddresses') ? $server->ipAddresses : $server->ipAddresses()->get();
                $anzahlIps = collect([$server->ip1, $server->ip2])->filter()->count() + $adressen->count();
            @endphp

            <x-slot:head>
                <x-show.header can="server_update" editUrl="{{ route('server.edit', [$customer, $server]) }}">
                    {{-- Rustdesk bleibt der erste Knopf in der Kopfzeile: taeglich benutzt. --}}
                    @if ($server->remoteID AND $server->remotePassword)
                        <x-input.linkbutton link="rustdesk://connection/new/{{ $server->remoteID }}?password={{ $server->remotePassword }}">
                            <x-slot:label>
                                <x-svg.software.rustdesk class="h-6 w-6 !fill-cerulean-500 hover:!fill-cerulean-400" />
                            </x-slot:label>
                        </x-input.linkbutton>
                    @endif
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
                        @if ($server->ip1)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$server->ip1" />
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

                {{-- Die Rustdesk-Kennung auch als Text: Wenn der Knopf nicht greift
                     (anderer Rechner, kein Client), braucht man die ID zum Abtippen. --}}
                <x-minitablecard :title="__('Fernwartung')" :array="[
                    'Rustdesk ID' => $server->remoteID,
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

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">
        {{ $servers->links() }}
    </div>

</x-app-layout>
