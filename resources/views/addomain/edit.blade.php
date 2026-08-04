<x-app-layout :$customer>
    <x-create.main :header="__('AD-Domäne bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('addomain.update', [$customer, $addomain]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Domäne')" name="domain" :default="$addomain->domain" />

        <x-create.singlerow :label="__('NETBIOS')" name="netbios" :default="$addomain->netbios" />

        <x-create.singlerow :label="__('DSRM Passwort')" name="dsrmpassword" :default="$addomain->dsrmpassword" />

        <x-edit.hidden hidden="{{ $addomain->hidden }}" />

    </x-create.main>

    @can('addomain_delete')
        <x-deletecard action="{{ route('addomain.destroy', [$customer, $addomain]) }}" />
    @endcan

</x-app-layout>
