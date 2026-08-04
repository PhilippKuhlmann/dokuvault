<x-app-layout :$customer>
    <x-create.main :header="__('Neue Domain')" action="{{ route('domain.store', $customer) }}">
        <x-create.singlerow :label="__('Domain')" name="name" />
        <x-create.doublerow :label1="__('Registrar')" name1="registrar" :label2="__('Ablaufdatum')" name2="expiry_date" type2="date" />
        <x-create.doublerow :label1="__('Nameserver 1')" name1="nameserver1" :label2="__('Nameserver 2')" name2="nameserver2" />
        <x-create.singlerow :label="__('Notizen')" name="notes" />
    </x-create.main>
</x-app-layout>
