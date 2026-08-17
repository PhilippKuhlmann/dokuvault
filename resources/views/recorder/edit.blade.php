<x-app-layout :$customer>
    <x-create.main :header="__('Recorder bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('recorder.update', [$customer, $recorder]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $recorder->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$recorder->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$recorder->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$recorder->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$recorder->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Port')" name="port" type="number" :default="$recorder->port" />

            <x-create.singlerow :label="__('Benutzer')" name="username" :default="$recorder->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$recorder->password" />
        </x-create.abschnitt>

        <x-create.beschaffung :model="$recorder" />

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$recorder" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$recorder" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('recorder_delete')
        <x-deletecard action="{{ route('recorder.destroy', [$customer, $recorder]) }}" breit />
    @endcan

</x-app-layout>
