<x-app-layout :$customer>

    <x-sitetopmenu can="nas_create" />


    @forelse ($nasList as $nas)

        @php
            $adressen = $nas->relationLoaded('ipAddresses') ? $nas->ipAddresses : $nas->ipAddresses()->get();
            $primaer = $nas->ip1 ?? $nas->ip;
            $anzahlIps = collect([$primaer, $nas->ip2 ?? null])->filter()->count() + $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="nas_update" editUrl="{{ route('nas.edit', [$customer, $nas]) }}" >
                    {{ $nas->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($nas->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $nas->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$nas" />

                <x-minitablecard :title="__('Hardware')" :array="[
                    'Hersteller' => $nas->manufacturer,
                    'Modell' => $nas->model,
                    'Seriennummer' => $nas->serialNumber,
                ]" />

                <x-credentialscard :device="$nas" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $nas->port,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $nas->username,
                    'Passwort' => $nas->password,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">
        {{ $nasList->links() }}
    </div>

</x-app-layout>
