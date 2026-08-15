<x-app-layout :$customer>
    <x-create.main :header="__('Computer bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('computer.update', [$customer, $computer]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $computer->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$computer->name" />

            <x-edit.select.operatingsystem selector="{{ $computer->operatingSystem?->id }}" :$operatingSystems/>
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$computer->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$computer->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$computer->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Fernwartung')">
            <x-create.singlerow :label="__('Rustdesk ID')" name="remoteID" :default="$computer->remoteID" />

            <x-create.singlerow :label="__('Rustdesk Passwort')" name="remotePassword" :default="$computer->remotePassword" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$computer" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$computer" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('computer_delete')
        <x-deletecard action="{{ route('computer.destroy', [$customer, $computer]) }}" breit />
    @endcan

</x-app-layout>
