<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Computer')" action="{{ route('computer.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :label2="__('Model')" name2="model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />

        <x-create.singlerow :label="__('IP-Adresse')" name="ip" />

        <x-create.doublerow :label1="__('Rustdesk ID')" name1="remoteID" :label2="__('Rustdesk Passwort')" name2="remotePassword" />

        <x-create.select.operatingsystem :$operatingSystems/>

    </x-create.main>
</x-app-layout>
