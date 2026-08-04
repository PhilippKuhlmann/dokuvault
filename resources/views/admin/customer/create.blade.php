<x-admin-layout>
    <x-create.main :header="__('Neuer Kunde')" action="{{ route('admin.customer.store') }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('Kundenummer')" name="customer_number" />

    </x-create.main>
</x-admin-layout>
