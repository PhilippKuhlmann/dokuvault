<x-app-layout :$customer>
    <x-create.main :header="__('Drucker bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('printer.update', [$customer, $printer]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $printer->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$printer->name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$printer->manufacturer" :label2="__('Model')" name2="model" :default2="$printer->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$printer->serialNumber" />

        <x-create.singlerow :label="__('IP-Adresse')" name="ip" :default="$printer->ip" />

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :default1="$printer->username" :label2="__('Passwort')" name2="password" :default2="$printer->password" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$printer" :customer="$customer" />


    <livewire:device-credentials :model="$printer" :customer="$customer" />

    @can('printer_delete')
        <x-deletecard action="{{ route('printer.destroy', [$customer, $printer]) }}" />
    @endcan

</x-app-layout>
