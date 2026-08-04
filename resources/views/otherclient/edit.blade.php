<x-app-layout :$customer>
    <x-create.main :header="__('Sonstiges Gerät bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('otherclient.update', [$customer, $otherclient]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $otherclient->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$otherclient->name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$otherclient->manufacturer" :label2="__('Model')" name2="model" :default2="$otherclient->model" />

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$otherclient->serialNumber" />

        <x-create.doublerow :label1="__('IP-Adresse')" name1="ip" :default1="$otherclient->ip" :label2="__('Port')" name2="port" :default2="$otherclient->port" />

        <x-create.singlerow :label="__('URL')" name="url" :default="$otherclient->url" />

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :default1="$otherclient->username" :label2="__('Passwort')" name2="password" :default2="$otherclient->password" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$otherclient" :customer="$customer" />

    @can('otherclient_delete')
        <x-deletecard action="{{ route('otherclient.destroy', [$customer, $otherclient]) }}" />
    @endcan

</x-app-layout>
