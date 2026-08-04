<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Login für Recoder')" action="{{ route('loginrecorder.store', $customer) }}">

        <x-create.select.recorder :$recorders/>

        <x-create.singlerow :label="__('Benutzername')" name="username" />

        <x-create.singlerow :label="__('Passwort')" name="password" />

        <x-create.hidden />

    </x-create.main>
</x-app-layout>
