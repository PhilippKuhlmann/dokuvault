<x-app-layout :$customer>
    <x-create.main :header="__('Maschine bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('machine.update', [$customer, $machine]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $machine->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$machine->name" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$machine" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$machine" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('machine_delete')
        <x-deletecard action="{{ route('machine.destroy', [$customer, $machine]) }}" breit />
    @endcan

</x-app-layout>
