<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Server')" action="{{ route('server.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :label2="__('Model')" name2="model" />

        {{-- Ein Standserver hat keine Einbautiefe: Das Feld erscheint erst,
             wenn die Bauform 19 Zoll ist. --}}
        <div x-data="{ bauform: '{{ old('form_factor', 'rack') }}' }">

            <x-create.options :label="__('Bauform')" name="form_factor" :options="config('custom.server_form_factors')" default="rack" x-model="bauform" />

            <div x-show="bauform === 'rack'" x-cloak>
                <x-create.options :label="__('Einbautiefe')" name="full_depth" :options="config('custom.server_depths')" default="1" />

                <x-create.singlerow :label="__('Höheneinheiten (HE)')" name="height_units" type="number" default="1" />
            </div>

        </div>

        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />

        <x-create.doublerow :label1="__('IP 1')" name1="ip1" :label2="__('IP 2')" name2="ip2" />

        <x-create.singlerow :label="__('BMC IP')" name="bmcIp" />

        <x-create.doublerow :label1="__('BMC User')" name1="bmcUser" :label2="__('BMC Passwort')" name2="bmcPassword" />

        <x-create.doublerow :label1="__('Rustdesk ID')" name1="remoteID" :label2="__('Rustdesk Passwort')" name2="remotePassword" />

        <x-create.select.operatingsystem :$operatingSystems/>

        <x-create.singlerow :label="__('Dienste Bitte mit komma getrennt angeben (eins,zwei,drei)')" name="services" />

    </x-create.main>
</x-app-layout>
