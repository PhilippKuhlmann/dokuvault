<x-admin-layout>
    <x-create.main :header="__('Neues Betriebsystem')" action="{{ route('admin.operatingsystem.store') }}">

        <x-create.singlerow :label="__('Name')" name="name" />

    </x-create.main>
</x-admin-layout>
