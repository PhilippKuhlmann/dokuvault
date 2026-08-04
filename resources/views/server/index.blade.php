<x-app-layout :$customer>

    <x-sitetopmenu can="server_create" />


    @forelse ($servers as $server)
        <x-card>
            <x-slot:head>
                <x-show.header can="server_update" editUrl="{{ route('server.edit', [$customer, $server]) }}">
                    @if ($server->remoteID AND $server->remotePassword)
                        <x-input.linkbutton link="rustdesk://connection/new/{{ $server->remoteID }}?password={{ $server->remotePassword }}">
                            <x-slot:label>
                                <x-svg.software.rustdesk class="h-6 w-6 !fill-cerulean-500 hover:!fill-cerulean-400" />
                            </x-slot:label>
                        </x-input.linkbutton>
                    @endif
                    {{ $server->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard title="Hardware" :array="[
                    'Hersteller' => $server->manufacturer,
                    'Modell' => $server->model,
                    'Seriennummer' => $server->serialNumber,
                    'Bauform' => config('custom.server_form_factors')[$server->form_factor] ?? null,
                    'Einbautiefe' => $server->form_factor === 'rack'
                        ? (config('custom.server_depths')[(int) $server->full_depth] ?? null)
                        : null,
                    'Höheneinheiten' => $server->form_factor === 'rack' ? $server->height_units.' HE' : null,
                ]" />

                <x-minitablecard title="Netzwerk" :array="[
                    'IP-Adresse 1' => $server->ip1,
                    'IP-Adresse 2' => $server->ip2,
                ]" />

                <x-minitablecard title="BMC" :array="[
                    'BMC IP-Adresse' => $server->bmcIp,
                    'BMC Benutzer' => $server->bmcUser,
                    'BMC Passwort' => $server->bmcPassword,
                ]" />

                <x-minitagcard title="Dienste" :array="$server->services" />

                <x-minitextcard title="Betriebsystem">
                    {{ $server->operatingSystem?->name ?? '—' }}
                </x-minitextcard>

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">
        {{ $servers->links() }}
    </div>

</x-app-layout>
