<x-app-layout :$customer>
    <x-create.main :header="__('Neue Webseite')" action="{{ route('loginwebsite.store', $customer) }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('URL')" name="url" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :label2="__('Passwort')" name2="password" />

        <x-create.hidden />

    </x-create.main>
</x-app-layout>
