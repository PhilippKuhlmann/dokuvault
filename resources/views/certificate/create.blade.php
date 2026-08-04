<x-app-layout :$customer>
    <x-create.main :header="__('Neues Zertifikat')" action="{{ route('certificate.store', $customer) }}">
        <x-create.singlerow :label="__('Bezeichnung')" name="name" />
        <x-create.doublerow :label1="__('Domain / CN')" name1="common_name" :label2="__('Aussteller')" name2="issuer" />
        <x-create.doublerow :label1="__('Typ')" name1="type" :label2="__('Ablaufdatum')" name2="expiry_date" type2="date" />
        <x-create.singlerow :label="__('Ausgestellt am')" name="issued_date" type="date" />
        <x-create.singlerow :label="__('Notizen')" name="notes" />
    </x-create.main>
</x-app-layout>
