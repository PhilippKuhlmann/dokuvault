<x-app-layout :$customer>
    <x-create.main :header="__('Computer bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('computer.update', [$customer, $computer]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" value="Standort" selector="{{ $computer->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$computer->name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$computer->manufacturer" :label2="__('Model')" name2="model" :default2="$computer->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$computer->serialNumber" />

        <x-create.singlerow :label="__('IP-Adresse')" name="ip" :default="$computer->ip" />

        <x-create.doublerow :label1="__('Rustdesk ID')" name1="remoteID" :default1="$computer->remoteID" :label2="__('Rustdesk Passwort')" name2="remotePassword" :default2="$computer->remotePassword" />

        <x-edit.select.operatingsystem selector="{{ $computer->operatingSystem?->id }}" :$operatingSystems/>

    </x-create.main>

    <livewire:device-ip-addresses :model="$computer" :customer="$customer" />

    @can('computer_delete')
        <x-deletecard action="{{ route('computer.destroy', [$customer, $computer]) }}" />
    @endcan

</x-app-layout>
