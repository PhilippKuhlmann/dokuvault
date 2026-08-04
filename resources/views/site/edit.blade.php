<x-app-layout :$customer>
    <x-create.main :header="__('Standort bearbeiten')" :labelsubmit="__('Speichern')"
        action="{{ route('site.update', [$customer, $site]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$site->name" />

        <x-create.doublerow14 :label1="__('Straße')" name1="street" :default1="$site->street" :label2="__('Hausnummer')" name2="house_number" :default2="$site->house_number" />

        <x-create.doublerow :label1="__('PLZ')" name1="zip" :default1="$site->zip" :label2="__('Stadt')" name2="city" :default2="$site->city" />

    </x-create.main>

    @can('site_delete')
        <x-deletecard action="{{ route('site.destroy', [$customer, $site]) }}" />
    @endcan

</x-app-layout>
