<x-app-layout :$customer>
    <x-create.main :header="__('TK-Anlage bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('phonesystem.update', [$customer, $phonesystem]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $phonesystem->site_id }}" :array="$sites" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$phonesystem->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$phonesystem->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$phonesystem->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Port')" name="port" type="number" :default="$phonesystem->port" />

            <x-create.singlerow :label="__('Benutzername')" name="username" :default="$phonesystem->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$phonesystem->password" />
        </x-create.abschnitt>

        <x-create.beschaffung :model="$phonesystem" />

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$phonesystem" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$phonesystem" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('phonesystem_delete')
        <x-deletecard action="{{ route('phonesystem.destroy', [$customer, $phonesystem]) }}" breit />
    @endcan

</x-app-layout>
