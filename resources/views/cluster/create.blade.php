<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Cluster')" action="{{ route('cluster.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.options :label="__('Art')" name="type" :options="config('custom.cluster_types')" />

        <x-create.singlerow :label="__('Notiz')" name="note" />

    </x-create.main>
</x-app-layout>
