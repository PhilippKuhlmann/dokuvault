<x-app-layout :$customer>

    <x-sitetopmenu can="accesspoint_create" />


    @forelse ($accesspoints as $accesspoint)

        @php
            $adressen = $accesspoint->relationLoaded('ipAddresses') ? $accesspoint->ipAddresses : $accesspoint->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="accesspoint_update" editUrl="{{ route('accesspoint.edit', [$customer, $accesspoint]) }}">
                    {{ $accesspoint->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$accesspoint" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $accesspoint->manufacturer,
                    'Modell' => $accesspoint->model,
                    'Seriennummer' => $accesspoint->serialNumber,
                ]" />

                <x-credentialscard :device="$accesspoint" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $accesspoint->username,
                    'Passwort' => $accesspoint->password,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $accesspoint->port,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $accesspoints->links() }}
    </div>

</x-app-layout>
