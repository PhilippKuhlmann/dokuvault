<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Ansprechpartner')" action="{{ route('contactperson.store', $customer) }}">

            <x-create.singlerow :label="__('Vorname')" name="first_name" />

            <x-create.singlerow :label="__('Nachname')" name="last_name" />

            <x-create.singlerow :label="__('Funktion')" name="role" />

            <x-create.singlerow :label="__('Telefonnummer')" name="phone" />

            <x-create.singlerow :label="__('E-Mail')" name="mail" />

    </x-create.main>
</x-app-layout>
