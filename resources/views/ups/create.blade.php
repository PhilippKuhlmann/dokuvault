<x-app-layout :$customer>
    <x-create.main :header="__('Neue USV')" action="{{ route('ups.store', $customer) }}">
        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />
        <x-create.singlerow :label="__('Name')" name="name" />
        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :label2="__('Model')" name2="model" />
        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />
        <x-create.singlerow :label="__('IP-Adresse')" name="ip" />
        <x-create.doublerow :label1="__('Kapazität (VA)')" name1="capacity" :label2="__('Laufzeit')" name2="runtime" />
        <x-create.singlerow :label="__('Notizen')" name="notes" />
    </x-create.main>
</x-app-layout>
