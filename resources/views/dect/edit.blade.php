<x-app-layout :$customer>
    <x-create.main :header="__('DECT bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('dect.update', [$customer, $dect]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $dect->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Rolle')" name="role" :default="$dect->role" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$dect->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$dect->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$dect->serialNumber" />

            <x-create.singlerow :label="__('MAC-Adresse')" name="mac" :default="$dect->mac" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Port')" name="port" type="number" :default="$dect->port" />

            <x-create.singlerow :label="__('Benutzername')" name="username" :default="$dect->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$dect->password" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$dect" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$dect" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('dect_delete')
        <x-deletecard action="{{ route('dect.destroy', [$customer, $dect]) }}" breit />
    @endcan

</x-app-layout>
