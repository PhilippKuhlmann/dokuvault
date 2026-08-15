<x-app-layout :$customer>
    <x-create.main :header="__('USV bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('ups.update', [$customer, $ups]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $ups->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$ups->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$ups->manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" :default="$ups->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$ups->serialNumber" />

            <x-create.singlerow :label="__('Kapazität (VA)')" name="capacity" :default="$ups->capacity" />

            <x-create.singlerow :label="__('Laufzeit')" name="runtime" :default="$ups->runtime" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Notizen')">
            <x-create.singlerow :label="__('Notizen')" name="notes" :default="$ups->notes" class="sm:col-span-2" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$ups" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$ups" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('ups_delete')
        <x-deletecard action="{{ route('ups.destroy', [$customer, $ups]) }}" breit />
    @endcan
</x-app-layout>
