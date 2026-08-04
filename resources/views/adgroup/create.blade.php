<x-app-layout :$customer>
    <x-create.main :header="__('Neue AD-Gruppe')" action="{{ route('adgroup.store', $customer) }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" />

    </x-create.main>
</x-app-layout>
