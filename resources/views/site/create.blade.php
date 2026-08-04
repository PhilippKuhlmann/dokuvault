<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Standort')" action="{{ route('site.store', $customer) }}">

            <x-create.singlerow :label="__('Name')" name="name" />

            <x-create.doublerow14 :label1="__('Straße')" name1="street" :label2="__('Hausnummer')" name2="house_number" />

            <x-create.doublerow :label1="__('PLZ')" name1="zip" :label2="__('Stadt')" name2="city" />

    </x-create.main>
</x-app-layout>
