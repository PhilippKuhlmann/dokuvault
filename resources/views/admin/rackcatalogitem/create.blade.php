<x-admin-layout>
    <x-create.main header="Neues Katalogelement" action="{{ route('admin.rackcatalogitem.store') }}">

        <x-create.singlerow label="Bezeichnung" name="name" />

        <x-create.doublerow
            label1="Höheneinheiten (HE)" name1="height_units" type1="number" default1="1"
            label2="Reihenfolge in der Palette" name2="sort_order" type2="number" default2="0" />

    </x-create.main>
</x-admin-layout>
