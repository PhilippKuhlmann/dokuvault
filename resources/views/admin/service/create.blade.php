<x-admin-layout>
    <x-create.main :header="__('Neuer Dienst')" action="{{ route('admin.service.store') }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        {{-- Steht bei der Auswahl im Geraeteformular und als Titel an der Kachel. --}}
        <x-create.singlerow :label="__('Beschreibung')" name="description" />

        <x-service.farbwahl :farbe="old('color', '#3391f0')" />

    </x-create.main>
</x-admin-layout>
