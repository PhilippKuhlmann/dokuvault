<x-app-layout :$customer>
    <x-create.main header="Serverschrank bearbeiten" labelsubmit="Speichern" action="{{ route('rack.update', [$customer, $rack]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" value="Standort" selector="{{ $rack->site_id }}" :array="$sites" />

        <x-create.singlerow label="Name" name="name" :default="$rack->name" />

        <x-create.doublerow label1="Höheneinheiten (HE)" name1="height_units" type1="number" :default1="$rack->height_units"
            label2="Ort (z. B. Serverraum EG)" name2="location" :default2="$rack->location" />

        <x-create.singlerow label="Notiz" name="note" :default="$rack->note" />

    </x-create.main>

    <livewire:rack-editor :rack="$rack" :customer="$customer" />

    @can('rack_delete')
        <x-deletecard action="{{ route('rack.destroy', [$customer, $rack]) }}" />
    @endcan

</x-app-layout>
