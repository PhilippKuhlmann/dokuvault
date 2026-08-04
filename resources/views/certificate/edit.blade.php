<x-app-layout :$customer>
    <x-create.main :header="__('Zertifikat bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('certificate.update', [$customer, $certificate]) }}">
        @method('PATCH')
        <x-create.singlerow :label="__('Bezeichnung')" name="name" :default="$certificate->name" />
        <x-create.doublerow :label1="__('Domain / CN')" name1="common_name" :default1="$certificate->common_name" :label2="__('Aussteller')" name2="issuer" :default2="$certificate->issuer" />
        <x-create.doublerow :label1="__('Typ')" name1="type" :default1="$certificate->type" :label2="__('Ablaufdatum')" name2="expiry_date" :default2="$certificate->expiry_date" type2="date" />
        <x-create.singlerow :label="__('Ausgestellt am')" name="issued_date" type="date" :default="$certificate->issued_date" />
        <x-create.singlerow :label="__('Notizen')" name="notes" :default="$certificate->notes" />
    </x-create.main>
    @can('certificate_delete')
        <x-deletecard action="{{ route('certificate.destroy', [$customer, $certificate]) }}" />
    @endcan
</x-app-layout>
