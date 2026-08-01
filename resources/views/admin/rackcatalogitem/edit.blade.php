<x-admin-layout>
    <x-create.main header="Katalogelement bearbeiten" labelsubmit="Speichern"
        action="{{ route('admin.rackcatalogitem.update', $rackCatalogItem) }}">
        @method('PATCH')

        @include('admin.rackcatalogitem._form', ['item' => $rackCatalogItem])

    </x-create.main>

    {{-- Loeschen wie in den Kunden-Formularen unter dem Formular, nicht in der Liste --}}
    <x-deletecard action="{{ route('admin.rackcatalogitem.destroy', $rackCatalogItem) }}" />
</x-admin-layout>
