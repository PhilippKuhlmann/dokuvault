<x-app-layout :$customer>
    <x-create.main :header="__('Zugriffs Lizenz bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('licenseaccess.update', [$customer, $licenseaccess]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$licenseaccess->name" />

        <x-create.singlerow :label="__('Key')" name="key" :default="$licenseaccess->key" />

        <x-input.label :value="__('Datei')" class="mt-2" />
        <x-input.file name="file" />

        <x-create.singlerow :label="__('Datei Name')" name="file_name" />

    </x-create.main>

    @can('licenseaccess_delete')
        <x-deletecard action="{{ route('licenseaccess.destroy', [$customer, $licenseaccess]) }}" />
    @endcan

</x-app-layout>

