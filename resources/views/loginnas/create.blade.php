<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Login für NAS')" action="{{ route('loginnas.store', $customer) }}">

        <x-create.select.nas :$nas/>

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :label2="__('Passwort')" name2="password" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" />

        <x-create.hidden />

    </x-create.main>
</x-app-layout>
