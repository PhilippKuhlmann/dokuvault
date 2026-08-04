<x-app-layout :$customer>
    <x-create.main :header="__('DECT bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('dect.update', [$customer, $dect]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $dect->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Rolle')" name="role" :default="$dect->role" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$dect->manufacturer" :label2="__('Model')" name2="model" :default2="$dect->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$dect->serialNumber" />

        <x-create.doublerow :label1="__('IP')" name1="ip" :default1="$dect->ip" :label2="__('Port')" name2="port" type2="number" :default2="$dect->port" />

        <x-create.singlerow :label="__('MAC-Adresse')" name="mac" :default="$dect->mac" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :default1="$dect->username" :label2="__('Passwort')" name2="password" :default2="$dect->password" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$dect" :customer="$customer" />

    @can('dect_delete')
        <x-deletecard action="{{ route('dect.destroy', [$customer, $dect]) }}" />
    @endcan

</x-app-layout>
