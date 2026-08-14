<x-admin-layout>
    <x-create.main :header="__('Dienst bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('admin.service.update', $service) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$service->name" />

        <x-service.farbwahl :farbe="old('color', $service->color)" :name="$service->name" />

    </x-create.main>

    <x-deletecard action="{{ route('admin.service.destroy', $service) }}" />
</x-admin-layout>
