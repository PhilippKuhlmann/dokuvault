<x-app-layout :$customer>
    <x-create.main :header="__('Neue Maschine')" action="{{ route('machine.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('IP')" name="ip" />

    </x-create.main>
</x-app-layout>
