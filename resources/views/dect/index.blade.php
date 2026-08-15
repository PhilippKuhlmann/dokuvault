<x-app-layout :$customer>

    <x-sitetopmenu can="dect_create" />

    @forelse ($dectList as $dect)

        @php
            $adressen = $dect->relationLoaded('ipAddresses') ? $dect->ipAddresses : $dect->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="dect_update" editUrl="{{ route('dect.edit', [$customer, $dect]) }}">
                    Rolle: {{ $dect->role }}

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


                <x-ipcard :device="$dect" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $dect->manufacturer,
                    'Modell' => $dect->model,
                    'Seriennummer' => $dect->serialNumber,
                ]" />

                <x-credentialscard :device="$dect" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $dect->port,
                    'MAC-Adresse' => $dect->mac,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $dect->username,
                    'Passwort' => $dect->password,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $dectList->links() }}
    </div>

</x-app-layout>
