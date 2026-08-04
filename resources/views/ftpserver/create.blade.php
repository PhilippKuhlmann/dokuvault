<x-app-layout :$customer>
    <x-create.main :header="__('Neuer FTP-Server User')" action="{{ route('ftpserver.store', $customer) }}">

        <x-create.singlerow :label="__('Host')" name="host" />

        <x-create.singlerow :label="__('Benutzername')" name="username" />

        <x-create.singlerow :label="__('Passwort')" name="password" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" />

    </x-create.main>
</x-app-layout>
