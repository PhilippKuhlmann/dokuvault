<x-app-layout :$customer>
    <x-create.main :header="__('Neues IoT Gerät')" action="{{ route('iotdevice.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :label2="__('Model')" name2="model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />

        <x-create.doublerow :label1="__('IP-Adresse')" name1="ip" :label2="__('Port')" name2="port" />

        <x-create.singlerow :label="__('URL')" name="url" />

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :label2="__('Passwort')" name2="password" />

    </x-create.main>
</x-app-layout>
