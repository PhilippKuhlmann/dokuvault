<x-admin-layout>
    <x-create.main :header="__('Katalogelement bearbeiten')" :labelsubmit="__('Speichern')"
        action="{{ route('admin.rackcatalogitem.update', $rackCatalogItem) }}">
        @method('PATCH')

        @include('admin.rackcatalogitem._form', ['item' => $rackCatalogItem])

    </x-create.main>

    {{-- Loeschen wie in den Kunden-Formularen unter dem Formular, nicht in der Liste.
         Eigener Hinweis: Ein Katalogeintrag kennt keinen Papierkorb, er ist
         danach weg - mitsamt einem hinterlegten Bild. Bereits verbaute
         Elemente bleiben stehen, sie haben die Bezeichnung kopiert. --}}
    <x-deletecard action="{{ route('admin.rackcatalogitem.destroy', $rackCatalogItem) }}"
        :hinweis="__('Der Eintrag wird endgültig gelöscht, mit ihm ein hinterlegtes Bild. Bereits eingebaute Elemente bleiben in den Racks stehen – dort wieder als Zeichnung.')"
        :frage="__('Katalogelement endgültig löschen?')" />
</x-admin-layout>
