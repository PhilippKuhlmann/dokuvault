<x-app-layout :$customer>
    <x-create.main :header="__('Camera bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('camera.update', [$customer, $camera]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $camera->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$camera->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$camera->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$camera->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$camera->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Port')" name="port" type="number" :default="$camera->port" />

            <x-create.singlerow :label="__('Benutzer')" name="username" :default="$camera->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$camera->password" />
        </x-create.abschnitt>

        <x-create.beschaffung :model="$camera" />

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$camera" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$camera" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('camera_delete')
        <x-deletecard action="{{ route('camera.destroy', [$customer, $camera]) }}" breit />
    @endcan

</x-app-layout>
