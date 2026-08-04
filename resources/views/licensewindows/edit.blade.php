<x-app-layout :$customer>
    <x-create.main :header="__('Windows Lizenz bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('licensewindows.update', [$customer, $licensewindows]) }}">
        @method('PATCH')

        <x-edit.select.operatingsystem selector="{{ $licensewindows->operatingSystem?->id }}" :$operatingSystems/>

        <x-create.singlerow :label="__('Key')" name="key" :default="$licensewindows->key" />

        <x-input.label :value="__('Datei')" class="mt-2" />
        <x-input.file name="file" />

        <x-create.singlerow :label="__('Datei Name')" name="file_name" />

    </x-create.main>

    @can('licensewindows_delete')
        <x-deletecard action="{{ route('licensewindows.destroy', [$customer, $licensewindows]) }}" />
    @endcan

</x-app-layout>

