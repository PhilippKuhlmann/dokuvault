<x-app-layout :$customer>
    <x-create.main :header="__('E-Mail-Archivierung bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('securepointuma.update', [$customer, $securepointuma]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$securepointuma->name" />

        <x-create.singlerow :label="__('Hersteller / Produkt')" name="manufacturer" :default="$securepointuma->manufacturer" />

        <x-create.singlerow :label="__('Type')" name="type" :default="$securepointuma->type" />

        <x-create.singlerow :label="__('Benutzername')" name="username" :default="$securepointuma->username" />

        <x-create.singlerow :label="__('Passwort')" name="password" :default="$securepointuma->password" />

        <x-create.singlerow :label="__('Verschlüsselungscode')" name="encryptionkey" :default="$securepointuma->encryptionkey" />

        <x-create.singlerow :label="__('IP')" name="ip" :default="$securepointuma->ip" />

        <x-create.singlerow :label="__('Admin URL')" name="urlAdmin" :default="$securepointuma->urlAdmin" />

        <x-create.singlerow :label="__('User URL')" name="urlUser" :default="$securepointuma->urlUser" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$securepointuma" :customer="$customer" />

    @can('securepointuma_delete')
        <x-deletecard action="{{ route('securepointuma.destroy', [$customer, $securepointuma]) }}" />
    @endcan

</x-app-layout>
