<x-app-layout :$customer>
    <x-create.main :header="__('E-Mail-Archivierung bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('securepointuma.update', [$customer, $securepointuma]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-create.singlerow :label="__('Name')" name="name" :default="$securepointuma->name" />

            <x-create.singlerow :label="__('Type')" name="type" :default="$securepointuma->type" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller / Produkt')" name="manufacturer" :default="$securepointuma->manufacturer" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Benutzername')" name="username" :default="$securepointuma->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$securepointuma->password" />

            <x-create.singlerow :label="__('Verschlüsselungscode')" name="encryptionkey" :default="$securepointuma->encryptionkey" />

            <x-create.singlerow :label="__('Admin URL')" name="urlAdmin" :default="$securepointuma->urlAdmin" />

            <x-create.singlerow :label="__('User URL')" name="urlUser" :default="$securepointuma->urlUser" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$securepointuma" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$securepointuma" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('securepointuma_delete')
        <x-deletecard action="{{ route('securepointuma.destroy', [$customer, $securepointuma]) }}" breit />
    @endcan

</x-app-layout>
