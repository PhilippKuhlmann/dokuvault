<x-app-layout :$customer>
    <x-create.main :header="__('Accesspoint bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('accesspoint.update', [$customer, $accesspoint]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $accesspoint->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$accesspoint->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$accesspoint->manufacturer" />

            <x-create.singlerow :label="__('Modell')" name="model" :default="$accesspoint->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$accesspoint->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Benutzername')" name="username" :default="$accesspoint->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$accesspoint->password" />

            <x-create.singlerow :label="__('Port')" name="port" :default="$accesspoint->port" type="number" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$accesspoint" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$accesspoint" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('accesspoint_delete')
        <x-deletecard action="{{ route('accesspoint.destroy', [$customer, $accesspoint]) }}" breit />
    @endcan

</x-app-layout>
