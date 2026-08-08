<x-app-layout :$customer>

    <x-sitetopmenu can="iotdevice_create" />


    @forelse ($iotdevices as $iotdevice)
    <x-card>
        <x-slot:head>
            <x-show.header can="iotdevice_update" editUrl="{{ route('iotdevice.edit', [$customer, $iotdevice]) }}">
                {{ $iotdevice->name }}
            </x-show.header>
        </x-slot>

        <x-slot:body>

            <x-minitablecard :title="__('Allgemein')" :array="[
                'Hersteller' => $iotdevice->manufacturer,
                'Modell' => $iotdevice->model,
                'Seriennummer' => $iotdevice->serialNumber,
            ]" />

            <x-credentialscard :device="$iotdevice" />

            <x-minitablecard :title="__('Netzwerk')" :array="[
                'IP-Adresse' => $iotdevice->ip,
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
