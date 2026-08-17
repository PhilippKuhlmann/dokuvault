<x-app-layout :$customer>
    <x-create.main :header="__('Sonstiges Gerät bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('otherclient.update', [$customer, $otherclient]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $otherclient->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$otherclient->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$otherclient->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$otherclient->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$otherclient->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Port')" name="port" :default="$otherclient->port" />

            <x-create.singlerow :label="__('URL')" name="url" :default="$otherclient->url" />

            <x-create.singlerow :label="__('Benutzer')" name="username" :default="$otherclient->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$otherclient->password" />
        </x-create.abschnitt>

        <x-create.beschaffung :model="$otherclient" />

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$otherclient" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$otherclient" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('otherclient_delete')
        <x-deletecard action="{{ route('otherclient.destroy', [$customer, $otherclient]) }}" breit />
    @endcan

</x-app-layout>
