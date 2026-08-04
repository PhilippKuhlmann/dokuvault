<x-app-layout :$customer>
    <x-create.main :header="__('Patchfeld bearbeiten')" :labelsubmit="__('Speichern')"
        action="{{ route('patchpanel.update', [$customer, $patchpanel]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $patchpanel->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$patchpanel->name" />

        <x-create.doublerow :label1="__('Portanzahl')" name1="port_count" type1="number" :default1="$patchpanel->port_count"
            :label2="__('Höheneinheiten (HE)')" name2="height_units" type2="number" :default2="$patchpanel->height_units" />

        <x-create.doublerow :label1="__('Hersteller')" name1="manufacturer" :default1="$patchpanel->manufacturer"
            :label2="__('Modell')" name2="model" :default2="$patchpanel->model" />

        <x-create.singlerow :label="__('Notiz')" name="note" :default="$patchpanel->note" />

    </x-create.main>

    <livewire:patch-panel-ports :panel="$patchpanel" :customer="$customer" />

    @can('patchpanel_delete')
        <x-deletecard action="{{ route('patchpanel.destroy', [$customer, $patchpanel]) }}" />
    @endcan

</x-app-layout>
