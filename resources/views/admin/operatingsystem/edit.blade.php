<x-admin-layout>
    <x-create.main :header="__('Betriebsystem bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('admin.operatingsystem.update', $operatingSystem) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$operatingSystem->name" />

    </x-create.main>
</x-admin-layout>
