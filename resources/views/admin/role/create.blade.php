<x-admin-layout>
    <x-create.main :header="__('Neue Rolle')" action="{{ route('admin.role.store') }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" />

        <x-slot:right>
            <x-role.permissions :matrix="$matrix" :others="$others" :actions="$actions" :admin-rechte="$adminRechte" />
        </x-slot>

    </x-create.main>
</x-admin-layout>
