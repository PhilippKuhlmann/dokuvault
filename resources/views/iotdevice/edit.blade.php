<x-app-layout :$customer>
    <x-create.main :header="__('IoT Gerät bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('iotdevice.update', [$customer, $iotdevice]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $iotdevice->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$iotdevice->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$iotdevice->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$iotdevice->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$iotdevice->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Port')" name="port" :default="$iotdevice->port" />

            <x-create.singlerow :label="__('URL')" name="url" :default="$iotdevice->url" />

            <x-create.singlerow :label="__('Benutzer')" name="username" :default="$iotdevice->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$iotdevice->password" />
        </x-create.abschnitt>

        <x-create.beschaffung :model="$iotdevice" />

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$iotdevice" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$iotdevice" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('iotdevice_delete')
        <x-deletecard action="{{ route('iotdevice.destroy', [$customer, $iotdevice]) }}" breit />
    @endcan

</x-app-layout>
