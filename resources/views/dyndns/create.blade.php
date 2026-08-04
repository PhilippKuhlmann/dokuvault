<x-app-layout :$customer>
    <x-create.main :header="__('Neuer DynDNS')" action="{{ route('dyndns.store', $customer) }}">

        <x-create.singlerow :label="__('Anbieter')" name="providor" />

        <x-create.singlerow :label="__('Domain')" name="domain" />

        <x-create.singlerow :label="__('Host')" name="host" />

        <x-create.singlerow :label="__('Benutzername')" name="username" />

        <x-create.singlerow :label="__('Passwort')" name="password" />

    </x-create.main>
</x-app-layout>
