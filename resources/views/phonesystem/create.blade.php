<x-app-layout :$customer>
    <x-create.main :header="__('Neue Telefonanlage')" action="{{ route('phonesystem.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :label2="__('Model')" name2="model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />

        <x-create.doublerow :label1="__('IP 1')" name1="ip1" :label2="__('Port')" name2="port" type2="number" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :label2="__('Passwort')" name2="password" />

    </x-create.main>
</x-app-layout>
