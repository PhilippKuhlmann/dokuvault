<x-app-layout :$customer>
    <x-create.main :header="__('TK-Anlage bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('phonesystem.update', [$customer, $phonesystem]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $phonesystem->site_id }}" :array="$sites" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$phonesystem->manufacturer" :label2="__('Model')" name2="model" :default2="$phonesystem->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$phonesystem->serialNumber" />

        <x-create.doublerow :label1="__('IP 1')" name1="ip1" :default1="$phonesystem->ip1" :label2="__('Port')" name2="port" type2="number" :default2="$phonesystem->port" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :default1="$phonesystem->username" :label2="__('Passwort')" name2="password" :default2="$phonesystem->password" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$phonesystem" :customer="$customer" />

    @can('phonesystem_delete')
        <x-deletecard action="{{ route('phonesystem.destroy', [$customer, $phonesystem]) }}" />
    @endcan

</x-app-layout>
