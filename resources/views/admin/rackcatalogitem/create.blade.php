<x-admin-layout>
    <x-create.main header="Neues Katalogelement" action="{{ route('admin.rackcatalogitem.store') }}">

        @include('admin.rackcatalogitem._form', ['item' => null])

    </x-create.main>
</x-admin-layout>
