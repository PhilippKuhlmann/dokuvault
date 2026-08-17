<x-app-layout :$customer>
    <x-create.main :header="__('NAS bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('nas.update', [$customer, $nas]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $nas->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$nas->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$nas->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$nas->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$nas->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Port')" name="port" :default="$nas->port" />

            <x-create.singlerow :label="__('Benutzer')" name="username" :default="$nas->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$nas->password" />
        </x-create.abschnitt>

        <x-create.beschaffung :model="$nas" />

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$nas" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$nas" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('nas_delete')
        <x-deletecard action="{{ route('nas.destroy', [$customer, $nas]) }}" breit />
    @endcan

</x-app-layout>

