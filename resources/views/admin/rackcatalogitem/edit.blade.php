<x-admin-layout>
    <x-create.main header="Katalogelement bearbeiten" labelsubmit="Speichern"
        action="{{ route('admin.rackcatalogitem.update', $rackCatalogItem) }}">
        @method('PATCH')

        <x-create.singlerow label="Bezeichnung" name="name" :default="$rackCatalogItem->name" />

        <x-create.doublerow
            label1="Höheneinheiten (HE)" name1="height_units" type1="number" :default1="$rackCatalogItem->height_units"
            label2="Reihenfolge in der Palette" name2="sort_order" type2="number" :default2="$rackCatalogItem->sort_order" />

    </x-create.main>

    {{-- Loeschen wie in den Kunden-Formularen unter dem Formular, nicht in der Liste --}}
    <x-deletecard action="{{ route('admin.rackcatalogitem.destroy', $rackCatalogItem) }}" />
</x-admin-layout>
