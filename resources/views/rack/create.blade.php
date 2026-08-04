<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Serverschrank')" action="{{ route('rack.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.doublerow :label1="__('Höheneinheiten (HE)')" name1="height_units" type1="number" :default1="42"
            :label2="__('Ort (z. B. Serverraum EG)')" name2="location" />

        <x-create.singlerow :label="__('Notiz')" name="note" />

    </x-create.main>
</x-app-layout>
