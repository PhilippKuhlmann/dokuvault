<x-app-layout :$customer>
    {{-- "Stammdaten speichern" statt "Speichern": Darunter stehen zwei Bloecke mit
         eigenem Speichern (IP-Adressen, Zugangsdaten). Der allgemeine Knopf liess
         glauben, er sichere auch die. --}}
    <x-create.main :header="__('Server bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('server.update', [$customer, $server]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-create.singlerow :label="__('Name')" name="name" :default="$server->name" />

            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $server->site_id }}" :array="$sites" />

            <x-edit.select.operatingsystem selector="{{ $server->operatingSystem?->id }}" :$operatingSystems/>
        </x-create.abschnitt>

        {{-- Ein Standserver hat keine Einbautiefe: Das Feld erscheint erst,
             wenn die Bauform 19 Zoll ist. --}}
        <x-create.abschnitt :titel="__('Hardware')" x-data="{ bauform: '{{ old('form_factor', $server->form_factor) }}' }">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$server->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$server->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$server->serialNumber" />

            <x-create.options :label="__('Bauform')" name="form_factor" :options="config('custom.server_form_factors')" :default="$server->form_factor" x-model="bauform" />

            {{-- Eigenes Raster ueber beide Spalten: x-show schaltet display um, ein
                 durchgereichtes "contents" wuerde mit x-cloak kollidieren. --}}
            <div x-show="bauform === 'rack'" x-cloak class="grid grid-cols-1 gap-x-4 sm:col-span-2 sm:grid-cols-2">
                <x-create.options :label="__('Einbautiefe')" name="full_depth" :options="config('custom.server_depths')" :default="(int) $server->full_depth" />

                <x-create.singlerow :label="__('Höheneinheiten (HE)')" name="height_units" type="number" :default="$server->height_units" />
            </div>
        </x-create.abschnitt>

        {{-- IP 1 und IP 2 sind hier raus: Adressen werden ausschliesslich im Block
             "Weitere IP-Adressen" gepflegt, wo Netz und Bezeichnung dranhaengen.
             Die Spalten bleiben in der Datenbank - Altbestand und der AutoDoc-Agent
             schreiben sie weiter, die Listen lesen sie weiter. --}}
        <x-create.abschnitt :titel="__('Fernwartung')">
            <x-create.singlerow :label="__('BMC IP')" name="bmcIp" :default="$server->bmcIp" class="sm:col-span-2" />

            <x-create.singlerow :label="__('BMC User')" name="bmcUser" :default="$server->bmcUser" />

            <x-create.singlerow :label="__('BMC Passwort')" name="bmcPassword" :default="$server->bmcPassword" />

            <x-create.singlerow :label="__('Rustdesk ID')" name="remoteID" :default="$server->remoteID" />

            <x-create.singlerow :label="__('Rustdesk Passwort')" name="remotePassword" :default="$server->remotePassword" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Dienste')" :hinweis="__('Mit Komma getrennt angeben (eins,zwei,drei)')">
            <x-create.singlerow :label="__('Dienste')" name="services" :default="implode(',', $server->services)" class="sm:col-span-2" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$server" :customer="$customer" eingebettet />

            <livewire:device-credentials :model="$server" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('server_delete')
        <x-deletecard action="{{ route('server.destroy', [$customer, $server]) }}" breit />
    @endcan

</x-app-layout>
