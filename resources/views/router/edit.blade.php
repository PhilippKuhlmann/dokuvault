<x-app-layout :$customer>
    <x-create.main :header="__('Router bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('router.update', [$customer, $router]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $router->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$router->name" />

        <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$router->manufacturer" />

        <x-create.doublerow :label1="__('Modell')" name1="model" :default1="$router->model" :label2="__('Seriennummer')" name2="serialNumber" :default2="$router->serialNumber" />

        <x-create.singlerow :label="__('Benutzername')" name="username" :default="$router->username" />

        <x-create.singlerow :label="__('Passwort')" name="password" :default="$router->password" />

        <x-create.doublerow14 :label1="__('IP')" name1="ip" :default1="$router->ip" :label2="__('Port')" name2="port" :default2="$router->port" type2="number" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$router" :customer="$customer" />

    @can('router_delete')
        <x-deletecard action="{{ route('router.destroy', [$customer, $router]) }}" />
    @endcan

</x-app-layout>
