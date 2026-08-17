<x-app-layout :$customer>
    <x-create.main :header="__('Drucker bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('printer.update', [$customer, $printer]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $printer->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$printer->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$printer->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$printer->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$printer->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Benutzer')" name="username" :default="$printer->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$printer->password" />
        </x-create.abschnitt>

        <x-create.beschaffung :model="$printer" />

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$printer" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$printer" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('printer_delete')
        <x-deletecard action="{{ route('printer.destroy', [$customer, $printer]) }}" breit />
    @endcan

</x-app-layout>
