<x-app-layout :$customer>
    <x-create.main :header="__('E-Mail-Archivierung hinzufügen')" action="{{ route('securepointuma.store', $customer) }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('Hersteller / Produkt')" name="manufacturer" />

        <x-create.singlerow :label="__('Type')" name="type"  />

        <x-create.singlerow :label="__('Benutzername')" name="username" />

        <x-create.singlerow :label="__('Passwort')" name="password" />

        <x-create.singlerow :label="__('Verschlüsselungscode')" name="encryptionkey" />

        <x-create.singlerow :label="__('IP')" name="ip" />

        <x-create.singlerow :label="__('Admin URL')" name="urlAdmin" />

        <x-create.singlerow :label="__('User URL')" name="urlUser" />

    </x-create.main>
</x-app-layout>
