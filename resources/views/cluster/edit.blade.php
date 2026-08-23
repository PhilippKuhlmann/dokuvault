<x-app-layout :$customer>
    <x-create.main :header="__('Cluster bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('cluster.update', [$customer, $cluster]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $cluster->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$cluster->name" />

        <x-create.options :label="__('Art')" name="type" :options="config('custom.cluster_types')" :default="$cluster->type" />

        <x-create.singlerow :label="__('Notiz')" name="note" :default="$cluster->note" />

    </x-create.main>

    @can('cluster_delete')
        <x-deletecard action="{{ route('cluster.destroy', [$customer, $cluster]) }}" />
    @endcan

</x-app-layout>
