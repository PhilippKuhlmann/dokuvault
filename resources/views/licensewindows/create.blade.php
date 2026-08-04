<x-app-layout :$customer>
    <x-create.main :header="__('Neue Windows Lizenz')" action="{{ route('licensewindows.store', $customer) }}">

        <x-create.select.operatingsystem :$operatingSystems/>

        <x-create.singlerow :label="__('Key')" name="key" />

        <x-input.label :value="__('Datei')" class="mt-2" />
        <x-input.file name="file" />

        <x-create.singlerow :label="__('Datei Name')" name="file_name" />

    </x-create.main>
</x-app-layout>
