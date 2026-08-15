<x-app-layout :$customer>
    <x-create.main :header="__('Telefon bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('phone.update', [$customer, $phone]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $phone->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Nebenstelle')" name="extension" :default="$phone->extension" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$phone->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$phone->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$phone->serialNumber" />

            <x-create.singlerow :label="__('MAC-Adresse')" name="mac" :default="$phone->mac" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Port')" name="port" type="number" :default="$phone->port" />

            <x-create.singlerow :label="__('Benutzername')" name="username" :default="$phone->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$phone->password" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$phone" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$phone" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('phone_delete')
        <x-deletecard action="{{ route('phone.destroy', [$customer, $phone]) }}" breit />
    @endcan

</x-app-layout>
