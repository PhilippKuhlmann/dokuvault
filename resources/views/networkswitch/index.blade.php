<x-app-layout :$customer>

    <x-sitetopmenu can="networkswitch_create" />


    @forelse ($networkswitches as $networkswitch)

        @php
            $adressen = $networkswitch->relationLoaded('ipAddresses') ? $networkswitch->ipAddresses : $networkswitch->ipAddresses()->get();
            $primaer = collect([$networkswitch->ip1 ?? null, $networkswitch->ip ?? null, $adressen->first()?->address])->filter()->first();
            $anzahlIps = collect([$primaer, $networkswitch->ip2 ?? null])->filter()->count() + $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="networkswitch_update" editUrl="{{ route('networkswitch.edit', [$customer, $networkswitch]) }}">
                    {{ $networkswitch->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($networkswitch->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $networkswitch->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$networkswitch" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $networkswitch->manufacturer,
                    'Modell' => $networkswitch->model,
                    'Seriennummer' => $networkswitch->serialNumber,
                ]" />

                <x-credentialscard :device="$networkswitch" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $networkswitch->username,
                    'Passwort' => $networkswitch->password,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $networkswitch->port,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $networkswitches->links() }}
    </div>

</x-app-layout>
