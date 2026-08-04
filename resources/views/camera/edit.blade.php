<x-app-layout :$customer>
    <x-create.main :header="__('Camera bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('camera.update', [$customer, $camera]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $camera->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$camera->name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$camera->manufacturer" :label2="__('Model')" name2="model" :default2="$camera->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$camera->serialNumber" />

        <x-create.doublerow14 :label1="__('IP')" name1="ip" :default1="$camera->ip" :label2="__('Port')" name2="port" type2="number" :default2="$camera->port" />

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :default1="$camera->username" :label2="__('Passwort')" name2="password" :default2="$camera->password" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$camera" :customer="$customer" />

    @can('camera_delete')
        <x-deletecard action="{{ route('camera.destroy', [$customer, $camera]) }}" />
    @endcan

</x-app-layout>
