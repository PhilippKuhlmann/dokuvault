<x-app-layout :$customer>
    <x-create.main :header="__('Neues Telefon')" action="{{ route('phone.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Nebenstelle')" name="extension" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :label2="__('Model')" name2="model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />

        <x-create.doublerow :label1="__('IP')" name1="ip" :label2="__('Port')" name2="port" type2="number" />

        <x-create.singlerow :label="__('MAC-Adresse')" name="mac" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :label2="__('Passwort')" name2="password" />

    </x-create.main>
</x-app-layout>
