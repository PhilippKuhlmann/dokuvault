<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Login')" action="{{ route('logingeneral.store', $customer) }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :label2="__('Passwort')" name2="password" />

        <x-create.hidden />

    </x-create.main>
</x-app-layout>
