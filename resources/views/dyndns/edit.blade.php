<x-app-layout :$customer>
    <x-create.main :header="__('DynDNS bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('dyndns.update', [$customer, $dyndns]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Anbieter')" name="providor" :default="$dyndns->providor" />

        <x-create.singlerow :label="__('Domain')" name="domain" :default="$dyndns->domain" />

        <x-create.singlerow :label="__('Host')" name="host" :default="$dyndns->host" />

        <x-create.singlerow :label="__('Benutzername')" name="username" :default="$dyndns->username" />

        <x-create.singlerow :label="__('Passwort')" name="password" :default="$dyndns->password" />

    </x-create.main>

    @can('dyndns_delete')
        <x-deletecard action="{{ route('dyndns.destroy', [$customer, $dyndns]) }}" />
    @endcan

</x-app-layout>
