<x-app-layout :$customer>
    <x-create.main :header="__('Neue NAS')" action="{{ route('nas.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :label2="__('Model')" name2="model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />

        <x-create.doublerow :label1="__('IP 1')" name1="ip1" :label2="__('IP 2')" name2="ip2" />

        <x-create.singlerow :label="__('Port')" name="port" />

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :label2="__('Passwort')" name2="password" />

    </x-create.main>
</x-app-layout>
