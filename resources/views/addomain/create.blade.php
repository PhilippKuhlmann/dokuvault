<x-app-layout :$customer>
    <x-create.main :header="__('Neue AD-Domäne')" action="{{ route('addomain.store', $customer) }}">

        <x-create.singlerow :label="__('Domäne')" name="domain" />

        <x-create.singlerow :label="__('NETBIOS')" name="netbios" />

        <x-create.singlerow :label="__('DSRM Passwort')" name="dsrmpassword" />

        <x-create.hidden />

    </x-create.main>
</x-app-layout>
