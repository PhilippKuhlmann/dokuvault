<x-app-layout :$customer>
    <x-create.main header="Neuer Server" action="{{ route('server.store', $customer) }}">

        <x-create.select name="site_id" value="Standort" :array="$sites" />

        <x-create.singlerow label="Name" name="name" />

        <x-create.doublerow label1="Hersteller" name1="manufacturer" label2="Model" name2="model" />

        {{-- Ein Standserver hat keine Einbautiefe: Das Feld erscheint erst,
             wenn die Bauform 19 Zoll ist. --}}
        <div x-data="{ bauform: '{{ old('form_factor', 'rack') }}' }">

            <x-create.options label="Bauform" name="form_factor" :options="config('custom.server_form_factors')" default="rack" x-model="bauform" />

            <div x-show="bauform === 'rack'" x-cloak>
                <x-create.options label="Einbautiefe" name="full_depth" :options="config('custom.server_depths')" default="1" />

                <x-create.singlerow label="Höheneinheiten (HE)" name="height_units" type="number" default="1" />
            </div>

        </div>

        <x-create.singlerow label="Seriennummer" name="serialNumber" />

        <x-create.doublerow label1="IP 1" name1="ip1" label2="IP 2" name2="ip2" />

        <x-create.singlerow label="BMC IP" name="bmcIp" />

        <x-create.doublerow label1="BMC User" name1="bmcUser" label2="BMC Passwort" name2="bmcPassword" />

        <x-create.doublerow label1="Rustdesk ID" name1="remoteID" label2="Rustdesk Passwort" name2="remotePassword" />

        <x-create.select.operatingsystem :$operatingSystems/>

        <x-create.singlerow label="Dienste Bitte mit komma getrennt angeben (eins,zwei,drei)" name="services" />

    </x-create.main>
</x-app-layout>
