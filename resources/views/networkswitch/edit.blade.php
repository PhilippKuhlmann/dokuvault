<x-app-layout :$customer>
    <x-create.main :header="__('Switch bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('networkswitch.update', [$customer, $networkswitch]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $networkswitch->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$networkswitch->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$networkswitch->manufacturer" />

            <x-create.singlerow :label="__('Modell')" name="model" :default="$networkswitch->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$networkswitch->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Benutzername')" name="username" :default="$networkswitch->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$networkswitch->password" />

            <x-create.singlerow :label="__('Port')" name="port" :default="$networkswitch->port" type="number" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$networkswitch" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$networkswitch" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('networkswitch_delete')
        <x-deletecard action="{{ route('networkswitch.destroy', [$customer, $networkswitch]) }}" breit />
    @endcan

</x-app-layout>
