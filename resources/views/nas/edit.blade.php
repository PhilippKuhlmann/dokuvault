<x-app-layout :$customer>
    <x-create.main :header="__('NAS bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('nas.update', [$customer, $nas]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $nas->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$nas->name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$nas->manufacturer" :label2="__('Model')" name2="model" :default2="$nas->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$nas->serialNumber" />

        <x-create.doublerow :label1="__('IP 1')" name1="ip1" :default1="$nas->ip1" :label2="__('IP 2')" name2="ip2" :default2="$nas->ip2" />

        <x-create.singlerow :label="__('Port')" name="port" :default="$nas->port" />

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :default1="$nas->username" :label2="__('Passwort')" name2="password" :default2="$nas->password" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$nas" :customer="$customer" />


    <livewire:device-credentials :model="$nas" :customer="$customer" />

    @can('nas_delete')
        <x-deletecard action="{{ route('nas.destroy', [$customer, $nas]) }}" />
    @endcan

</x-app-layout>

