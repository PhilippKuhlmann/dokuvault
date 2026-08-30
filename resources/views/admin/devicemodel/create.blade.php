<x-admin-layout>
    <x-create.main :header="__('Neues Gerätemodell')" action="{{ route('admin.devicemodel.store') }}">

        @include('admin.devicemodel._form', ['item' => null])

    </x-create.main>
</x-admin-layout>
