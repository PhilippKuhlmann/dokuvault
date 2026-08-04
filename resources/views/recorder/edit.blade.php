<x-app-layout :$customer>
    <x-create.main :header="__('Recorder bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('recorder.update', [$customer, $recorder]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $recorder->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$recorder->name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$recorder->manufacturer" :label2="__('Model')" name2="model" :default2="$recorder->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$recorder->serialNumber" />

        <x-create.doublerow14 :label1="__('IP')" name1="ip" :default1="$recorder->ip" :label2="__('Port')" name2="port" type2="number" :default2="$recorder->port" />

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :default1="$recorder->username" :label2="__('Passwort')" name2="password" :default2="$recorder->password" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$recorder" :customer="$customer" />

    @can('recorder_delete')
        <x-deletecard action="{{ route('recorder.destroy', [$customer, $recorder]) }}" />
    @endcan

</x-app-layout>
