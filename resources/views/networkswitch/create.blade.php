<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Switch')" action="{{ route('networkswitch.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('Hersteller')" name="manufacturer" />

        <x-create.doublerow :label1="__('Modell')" name1="model" :label2="__('Seriennummer')" name2="serialNumber" />

        <x-create.singlerow :label="__('Benutzername')" name="username" />

        <x-create.singlerow :label="__('Passwort')" name="password" />

        <x-create.doublerow14 :label1="__('IP')" name1="ip" :label2="__('Port')" name2="port" type2="number" />

    </x-create.main>
</x-app-layout>
