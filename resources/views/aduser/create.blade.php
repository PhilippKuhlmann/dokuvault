<x-app-layout :$customer>
    <x-create.main :header="__('Neuer AD-Benutzer')" action="{{ route('aduser.store', $customer) }}">

        <x-create.singlerow :label="__('Vorname')" name="firstName" />

        <x-create.singlerow :label="__('Nachname')" name="lastName" />

        <x-create.singlerow :label="__('Benutzername')" name="username" />

        <x-create.singlerow :label="__('E-Mail')" name="email" />

        <x-create.singlerow :label="__('Passwort')" name="password" />

        <x-create.radio :label="__('Status')" name="enabled" :radios="[
            'Aktiv' => 1,
            'Deaktiviert' => 0,
        ]" />

        <x-create.hidden />

    </x-create.main>
</x-app-layout>
