<x-app-layout :$customer>
    <x-create.main :header="__('USV bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('ups.update', [$customer, $ups]) }}">
        @method('PATCH')
        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $ups->site_id }}" :array="$sites" />
        <x-create.singlerow :label="__('Name')" name="name" :default="$ups->name" />
        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$ups->manufacturer" :label2="__('Model')" name2="model" :default2="$ups->model" />
        <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$ups->serialNumber" />
        <x-create.singlerow :label="__('IP-Adresse')" name="ip" :default="$ups->ip" />
        <x-create.doublerow :label1="__('Kapazität (VA)')" name1="capacity" :default1="$ups->capacity" :label2="__('Laufzeit')" name2="runtime" :default2="$ups->runtime" />
        <x-create.singlerow :label="__('Notizen')" name="notes" :default="$ups->notes" />
    </x-create.main>

    <livewire:device-ip-addresses :model="$ups" :customer="$customer" />
    @can('ups_delete')
        <x-deletecard action="{{ route('ups.destroy', [$customer, $ups]) }}" />
    @endcan
</x-app-layout>
