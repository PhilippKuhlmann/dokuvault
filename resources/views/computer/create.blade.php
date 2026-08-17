<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Computer')" action="{{ route('computer.store', $customer) }}" breit>
        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" />

            <x-create.select.operatingsystem :$operatingSystems/>
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Fernwartung')">
            <x-create.singlerow :label="__('Rustdesk ID')" name="remoteID" />

            <x-create.singlerow :label="__('Rustdesk Passwort')" name="remotePassword" />
        </x-create.abschnitt>

        {{-- Weitere IP-Adressen und Zugangsdaten haengen am gespeicherten Objekt;
             beide erscheinen direkt nach dem Anlegen im Bearbeiten-Formular.
             Das steht hier, damit ihr Fehlen nicht wie ein Mangel aussieht. --}}
        <x-create.beschaffung />

        <x-create.abschnitt :titel="__('IP-Adressen und Zugangsdaten')">
            <p class="mt-1 text-sm text-gray-500 sm:col-span-2 dark:text-gray-400">
                {{ __('Lassen sich eintragen, sobald das Gerät angelegt ist.') }}
            </p>
        </x-create.abschnitt>

    </x-create.main>
</x-app-layout>
