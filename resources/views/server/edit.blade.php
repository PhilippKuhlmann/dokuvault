<x-app-layout :$customer>
    <x-create.main header="Server bearbeiten" labelsubmit="Speichern" action="{{ route('server.update', [$customer, $server]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" value="Standort" selector="{{ $server->site_id }}" :array="$sites" />

        <x-create.singlerow label="Name" name="name" :default="$server->name" />

        <x-create.doublerow label1="Hersteller" name1="manufacturer" :default1="$server->manufacturer" label2="Model" name2="model" :default2="$server->model" />

        {{-- Ein Standserver hat keine Einbautiefe: Das Feld erscheint erst,
             wenn die Bauform 19 Zoll ist. --}}
        <div x-data="{ bauform: '{{ old('form_factor', $server->form_factor) }}' }">

            <x-create.options label="Bauform" name="form_factor" :options="config('custom.server_form_factors')" :default="$server->form_factor" x-model="bauform" />

            <div x-show="bauform === 'rack'" x-cloak>
                <x-create.options label="Einbautiefe" name="full_depth" :options="config('custom.server_depths')" :default="(int) $server->full_depth" />

                <x-create.singlerow label="Höheneinheiten (HE)" name="height_units" type="number" :default="$server->height_units" />
            </div>

        </div>

        <x-create.singlerow label="Seriennummer" name="serialNumber" :default="$server->serialNumber" />

        <x-create.doublerow label1="IP 1" name1="ip1" :default1="$server->ip1" label2="IP 2" name2="ip2" :default2="$server->ip2" />

        <x-create.singlerow label="BMC IP" name="bmcIp" :default="$server->bmcIp" />

        <x-create.doublerow label1="BMC User" name1="bmcUser" :default1="$server->bmcUser" label2="BMC Passwort" name2="bmcPassword" :default2="$server->bmcPassword" />

        <x-create.doublerow label1="Rustdesk ID" name1="remoteID" :default1="$server->remoteID" label2="Rustdesk Passwort" name2="remotePassword" :default2="$server->remotePassword" />

        <x-edit.select.operatingsystem selector="{{ $server->operatingSystem?->id }}" :$operatingSystems/>

        <x-create.singlerow label="Dienste Bitte mit komma getrennt angeben (eins,zwei,drei)" name="services" :default="implode(',', $server->services)" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$server" :customer="$customer" />

    @can('server_delete')
        <x-deletecard action="{{ route('server.destroy', [$customer, $server]) }}" />
    @endcan

</x-app-layout>

