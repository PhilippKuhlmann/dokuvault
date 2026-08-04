<x-app-layout :$customer>
    <x-create.main :header="__('Telefon bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('phone.update', [$customer, $phone]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" value="Standort" selector="{{ $phone->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Nebenstelle')" name="extension" :default="$phone->extension" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$phone->manufacturer" :label2="__('Model')" name2="model" :default2="$phone->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$phone->serialNumber" />

        <x-create.doublerow :label1="__('IP')" name1="ip" :default1="$phone->ip" :label2="__('Port')" name2="port" type2="number" :default2="$phone->port" />

        <x-create.singlerow :label="__('MAC-Adresse')" name="mac" :default="$phone->mac" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :default1="$phone->username" :label2="__('Passwort')" name2="password" :default2="$phone->password" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$phone" :customer="$customer" />

    @can('phone_delete')
        <x-deletecard action="{{ route('phone.destroy', [$customer, $phone]) }}" />
    @endcan

</x-app-layout>
