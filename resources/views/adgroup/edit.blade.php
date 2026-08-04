<x-app-layout :$customer>
    <x-create.main :header="__('AD-Gruppe bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('adgroup.update', [$customer, $adgroup]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$adgroup->name" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" :default="$adgroup->description" />

    </x-create.main>

    @can('adgroup_delete')
        <x-deletecard action="{{ route('adgroup.destroy', [$customer, $adgroup]) }}" />
    @endcan

</x-app-layout>
