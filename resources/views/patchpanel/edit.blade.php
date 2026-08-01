<x-app-layout :$customer>
    <x-create.main header="Patchfeld bearbeiten" labelsubmit="Speichern"
        action="{{ route('patchpanel.update', [$customer, $patchpanel]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" value="Standort" selector="{{ $patchpanel->site_id }}" :array="$sites" />

        <x-create.singlerow label="Name" name="name" :default="$patchpanel->name" />

        <x-create.doublerow label1="Portanzahl" name1="port_count" type1="number" :default1="$patchpanel->port_count"
            label2="Höheneinheiten (HE)" name2="height_units" type2="number" :default2="$patchpanel->height_units" />

        <x-create.doublerow label1="Hersteller" name1="manufacturer" :default1="$patchpanel->manufacturer"
            label2="Modell" name2="model" :default2="$patchpanel->model" />

        <x-create.singlerow label="Notiz" name="note" :default="$patchpanel->note" />

    </x-create.main>

    <livewire:patch-panel-ports :panel="$patchpanel" :customer="$customer" />

    @can('patchpanel_delete')
        <x-deletecard action="{{ route('patchpanel.destroy', [$customer, $patchpanel]) }}" />
    @endcan

</x-app-layout>
