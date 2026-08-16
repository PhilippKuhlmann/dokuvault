<x-admin-layout>
    <x-create.main :header="__('Neues Betriebssystem')" action="{{ route('admin.operatingsystem.store') }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('Support-Ende (EOL)')" name="eol_date" type="date" />

    </x-create.main>
</x-admin-layout>
