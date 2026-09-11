<x-app-layout :$customer>
    {{-- breit: Der Bestueckungs-Editor darunter braucht max-w-5xl fuer Palette,
         Schema und Frontansicht nebeneinander. Stand das Formular auf der
         schmaleren Lesebreite, ragte die Karte in der Mitte links und rechts
         heraus - drei gestapelte Karten, von denen eine breiter ist, sehen aus
         wie ein Fehler. --}}
    <x-create.main :breit="true" :header="__('Serverschrank bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('rack.update', [$customer, $rack]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $rack->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$rack->name" />

        <x-create.doublerow :label1="__('Höheneinheiten (HE)')" name1="height_units" type1="number" :default1="$rack->height_units"
            :label2="__('Ort (z. B. Serverraum EG)')" name2="location" :default2="$rack->location" />

        <x-create.singlerow :label="__('Notiz')" name="note" :default="$rack->note" />

    </x-create.main>

    <livewire:rack-editor :rack="$rack" :customer="$customer" />

    @can('rack_delete')
        <x-deletecard :breit="true" action="{{ route('rack.destroy', [$customer, $rack]) }}" />
    @endcan

</x-app-layout>
