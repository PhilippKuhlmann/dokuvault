<x-admin-layout>
    <x-create.main :header="__('Kunde bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('admin.customer.update', $customer) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$customer->name" />

        <x-create.singlerow :label="__('Kundenummer')" name="customer_number" :default="$customer->customer_number" />

    </x-create.main>
</x-admin-layout>
