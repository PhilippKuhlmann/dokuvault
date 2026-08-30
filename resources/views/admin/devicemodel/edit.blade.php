<x-admin-layout>
    <x-create.main :header="__('Gerätemodell bearbeiten')" :labelsubmit="__('Speichern')"
        action="{{ route('admin.devicemodel.update', $deviceModel) }}">
        @method('PATCH')

        @include('admin.devicemodel._form', ['item' => $deviceModel])

    </x-create.main>

    {{-- Eigener Hinweis: Ein Geraetemodell kennt keinen Papierkorb. Geraete
         haengen aber auch nicht daran - sie fuehren Hersteller und Modell
         selbst, es verschwindet nur das Foto. --}}
    <x-deletecard action="{{ route('admin.devicemodel.destroy', $deviceModel) }}"
        :hinweis="__('Der Eintrag wird endgültig gelöscht, mit ihm das Bild. Die Geräte selbst bleiben unberührt – sie führen Hersteller und Modell in ihrer eigenen Dokumentation und erscheinen danach wieder als Zeichnung.')"
        :frage="__('Gerätemodell endgültig löschen?')" />
</x-admin-layout>
