<x-app-layout :$customer>
    <x-create.main :header="__('Neues Patchfeld')" action="{{ route('patchpanel.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.doublerow :label1="__('Portanzahl')" name1="port_count" type1="number" :default1="24"
            :label2="__('Höheneinheiten (HE)')" name2="height_units" type2="number" :default2="1" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :label2="__('Modell')" name2="model" />

        <x-create.singlerow :label="__('Notiz')" name="note" />

        <p class="mt-4 text-sm text-gray-400 dark:text-gray-500">
            {{ __('Die Ports werden beim Speichern angelegt und lassen sich anschließend einzeln beschriften.') }}
        </p>

    </x-create.main>
</x-app-layout>
