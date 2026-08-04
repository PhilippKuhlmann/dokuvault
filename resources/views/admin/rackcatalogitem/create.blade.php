<x-admin-layout>
    <x-create.main :header="__('Neues Katalogelement')" action="{{ route('admin.rackcatalogitem.store') }}">

        @include('admin.rackcatalogitem._form', ['item' => null])

    </x-create.main>
</x-admin-layout>
