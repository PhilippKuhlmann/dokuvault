<x-app-layout :$customer>
    <x-create.main :header="__('Domain bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('domain.update', [$customer, $domain]) }}">
        @method('PATCH')
        <x-create.singlerow :label="__('Domain')" name="name" :default="$domain->name" />
        <x-create.doublerow :label1="__('Registrar')" name1="registrar" :default1="$domain->registrar" :label2="__('Ablaufdatum')" name2="expiry_date" :default2="$domain->expiry_date" type2="date" />
        <x-create.doublerow :label1="__('Nameserver 1')" name1="nameserver1" :default1="$domain->nameserver1" :label2="__('Nameserver 2')" name2="nameserver2" :default2="$domain->nameserver2" />
        <x-create.singlerow :label="__('Notizen')" name="notes" :default="$domain->notes" />
    </x-create.main>
    @can('domain_delete')
        <x-deletecard action="{{ route('domain.destroy', [$customer, $domain]) }}" />
    @endcan
</x-app-layout>
