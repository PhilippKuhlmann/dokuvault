<x-app-layout :$customer>
    <x-create.main :header="__('Router bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('router.update', [$customer, $router]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $router->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$router->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$router->manufacturer" />

            <x-create.singlerow :label="__('Modell')" name="model" :default="$router->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$router->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Benutzername')" name="username" :default="$router->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$router->password" />

            <x-create.singlerow :label="__('Port')" name="port" :default="$router->port" type="number" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$router" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$router" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('router_delete')
        <x-deletecard action="{{ route('router.destroy', [$customer, $router]) }}" breit />
    @endcan

</x-app-layout>
