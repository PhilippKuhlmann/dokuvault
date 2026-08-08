<x-app-layout :$customer>
    <x-create.main :header="__('Accesspoint bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('accesspoint.update', [$customer, $accesspoint]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $accesspoint->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$accesspoint->name" />

        <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$accesspoint->manufacturer" />

        <x-create.doublerow :label1="__('Modell')" name1="model" :default1="$accesspoint->model" :label2="__('Seriennummer')" name2="serialNumber" :default2="$accesspoint->serialNumber" />

        <x-create.singlerow :label="__('Benutzername')" name="username" :default="$accesspoint->username" />

        <x-create.singlerow :label="__('Passwort')" name="password" :default="$accesspoint->password" />

        <x-create.doublerow14 :label1="__('IP')" name1="ip" :default1="$accesspoint->ip" :label2="__('Port')" name2="port" :default2="$accesspoint->port" type2="number" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$accesspoint" :customer="$customer" />


    <livewire:device-credentials :model="$accesspoint" :customer="$customer" />

    @can('accesspoint_delete')
        <x-deletecard action="{{ route('accesspoint.destroy', [$customer, $accesspoint]) }}" />
    @endcan

</x-app-layout>
