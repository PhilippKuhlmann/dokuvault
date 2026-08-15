<x-app-layout :$customer>

    <x-sitetopmenu can="otherclient_create" />


    @forelse ($otherclients as $otherclient)

        @php
            $adressen = $otherclient->relationLoaded('ipAddresses') ? $otherclient->ipAddresses : $otherclient->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="otherclient_update" editUrl="{{ route('otherclient.edit', [$customer, $otherclient]) }}">
                    {{ $otherclient->name }}

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


                <x-ipcard :device="$otherclient" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $otherclient->manufacturer,
                    'Modell' => $otherclient->model,
                    'Seriennummer' => $otherclient->serialNumber,
                ]" />

                <x-credentialscard :device="$otherclient" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $otherclient->port,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $otherclient->username,
                    'Passwort' => $otherclient->password
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse


    <div class="px-3 pb-3">
        {{ $otherclients->links() }}
    </div>

</x-app-layout>
