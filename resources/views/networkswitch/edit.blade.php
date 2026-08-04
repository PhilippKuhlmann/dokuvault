<x-app-layout :$customer>
    <x-create.main :header="__('Switch bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('networkswitch.update', [$customer, $networkswitch]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" value="Standort" selector="{{ $networkswitch->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$networkswitch->name" />

        <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$networkswitch->manufacturer" />

        <x-create.doublerow :label1="__('Modell')" name1="model" :default1="$networkswitch->model" :label2="__('Seriennummer')" name2="serialNumber" :default2="$networkswitch->serialNumber" />

        <x-create.singlerow :label="__('Benutzername')" name="username" :default="$networkswitch->username" />

        <x-create.singlerow :label="__('Passwort')" name="password" :default="$networkswitch->password" />

        <x-create.doublerow14 :label1="__('IP')" name1="ip" :default1="$networkswitch->ip" :label2="__('Port')" name2="port" :default2="$networkswitch->port" type2="number" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$networkswitch" :customer="$customer" />

    @can('networkswitch_delete')
        <x-deletecard action="{{ route('networkswitch.destroy', [$customer, $networkswitch]) }}" />
    @endcan

</x-app-layout>
