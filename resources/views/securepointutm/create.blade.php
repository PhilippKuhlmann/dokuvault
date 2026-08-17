<x-app-layout :$customer>
    <x-create.main :header="__('Neue Securepoint UTM')" action="{{ route('securepointutm.store', $customer) }}" breit>
        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" />

            <x-create.singlerow :label="__('Type')" name="type" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Benutzername')" name="username" />

            <x-create.singlerow :label="__('Passwort')" name="password" />

            <x-create.singlerow :label="__('Cloud Backup Passwort')" name="cloudBackupPassword" />

            <x-create.singlerow :label="__('USC-PIN')" name="uscpin" />

            <x-create.singlerow :label="__('Admin URL')" name="urlAdmin" />

            <x-create.singlerow :label="__('User URL')" name="urlUser" />

            <x-create.singlerow :label="__('Externe URL')" name="urlExternal" />
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
