<x-app-layout :$customer>
    <x-create.main :header="__('Maschine bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('machine.update', [$customer, $machine]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" value="Standort" selector="{{ $machine->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$machine->name" />

        <x-create.singlerow :label="__('IP')" name="ip" :default="$machine->ip" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$machine" :customer="$customer" />

    @can('machine_delete')
        <x-deletecard action="{{ route('machine.destroy', [$customer, $machine]) }}" />
    @endcan

</x-app-layout>
