<x-app-layout :$customer>
    <x-create.main :header="__('IoT Gerät bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('iotdevice.update', [$customer, $iotdevice]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $iotdevice->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$iotdevice->name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$iotdevice->manufacturer" :label2="__('Model')" name2="model" :default2="$iotdevice->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$iotdevice->serialNumber" />

        <x-create.doublerow :label1="__('IP-Adresse')" name1="ip" :default1="$iotdevice->ip" :label2="__('Port')" name2="port" :default2="$iotdevice->port" />

        <x-create.singlerow :label="__('URL')" name="url" :default="$iotdevice->url" />

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :default1="$iotdevice->username" :label2="__('Passwort')" name2="password" :default2="$iotdevice->password" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$iotdevice" :customer="$customer" />


    <livewire:device-credentials :model="$iotdevice" :customer="$customer" />

    @can('iotdevice_delete')
        <x-deletecard action="{{ route('iotdevice.destroy', [$customer, $iotdevice]) }}" />
    @endcan

</x-app-layout>
