<x-app-layout :$customer>

    <x-sitetopmenu can="iotdevice_create" />


    @forelse ($iotdevices as $iotdevice)

        @php
            $adressen = $iotdevice->relationLoaded('ipAddresses') ? $iotdevice->ipAddresses : $iotdevice->ipAddresses()->get();
            $primaer = $iotdevice->ip1 ?? $iotdevice->ip;
            $anzahlIps = collect([$primaer, $iotdevice->ip2 ?? null])->filter()->count() + $adressen->count();
        @endphp
    <x-card>
        <x-slot:head>
            <x-show.header can="iotdevice_update" editUrl="{{ route('iotdevice.edit', [$customer, $iotdevice]) }}">
                {{ $iotdevice->name }}

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


            <x-ipcard :device="$iotdevice" />

            <x-minitablecard :title="__('Allgemein')" :array="[
                'Hersteller' => $iotdevice->manufacturer,
                'Modell' => $iotdevice->model,
                'Seriennummer' => $iotdevice->serialNumber,
            ]" />

            <x-credentialscard :device="$iotdevice" />

            <x-minitablecard :title="__('Netzwerk')" :array="[
                'Port' => $iotdevice->port,
                'URL' => $iotdevice->url,
            ]" />

            <x-minitablecard :title="__('Login')" :array="[
                'Benutzer' => $iotdevice->username,
                'Passwort' => $iotdevice->password
            ]" />

        </x-slot>
    </x-card>
@empty
    <x-emptystate />
@endforelse


    <div class="px-3 pb-3">
        {{ $iotdevices->links() }}
    </div>

</x-app-layout>
