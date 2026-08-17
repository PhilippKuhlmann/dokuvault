<x-app-layout :$customer>
    <x-create.main :header="__('Neue Firewall')" action="{{ route('firewall.store', $customer) }}" breit>
        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" />
        </x-create.abschnitt>

        {{-- Der Hersteller steuert, ob die Securepoint-Felder erscheinen: Eine UTM
             hat eine USC-PIN und ein Cloud-Backup-Kennwort, kein anderes Geraet.
             Freitext statt Auswahlliste, damit jeder Hersteller eintragbar
             bleibt - deshalb wird verglichen und nicht auf Gleichheit geprueft. --}}
        <x-create.abschnitt :titel="__('Hardware')"
            x-data="{ hersteller: '{{ old('manufacturer', '') }}' }">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" x-model="hersteller" />

            <x-create.singlerow :label="__('Modell')" name="model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />

            <x-create.singlerow :label="__('Firmware')" name="firmware" />

            <x-create.options :label="__('Bauform')" name="form_factor"
                :options="config('custom.firewall_form_factors')" default="appliance" />

            <div x-show="hersteller.toLowerCase().includes('securepoint')" x-cloak
                class="grid grid-cols-1 gap-x-4 sm:col-span-2 sm:grid-cols-2">
                <x-create.singlerow :label="__('USC-PIN')" name="usc_pin" />

                <x-create.singlerow :label="__('Cloud-Backup-Kennwort')" name="cloud_backup_password" />

                <x-create.singlerow :label="__('Benutzerportal')" name="url_user" />

                <x-create.singlerow :label="__('Externer Zugang')" name="url_external" />
            </div>
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Verwaltungsoberfläche')" name="management_url" />

            <x-create.singlerow :label="__('Benutzername')" name="username" />

            <x-create.singlerow :label="__('Passwort')" name="password" />

            <x-create.singlerow :label="__('Port')" name="port" type="number" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Subscription')">
            {{-- Ohne gueltige Subscription bekommt eine UTM keine Signaturen
                 mehr. Das Datum steht deshalb in einem eigenen Abschnitt und
                 nicht bei der Hardware-Garantie. --}}
            <x-create.singlerow :label="__('Subscription bis')" name="subscription_until" type="date" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Sonstiges')">
            <x-create.singlerow :label="__('Notizen')" name="notes" />
        </x-create.abschnitt>

        {{-- Weitere IP-Adressen und Zugangsdaten haengen am gespeicherten Objekt;
             beide erscheinen direkt nach dem Anlegen im Bearbeiten-Formular. --}}
        <x-create.beschaffung />

        <x-create.abschnitt :titel="__('IP-Adressen und Zugangsdaten')">
            <p class="mt-1 text-sm text-gray-500 sm:col-span-2 dark:text-gray-400">
                {{ __('Lassen sich eintragen, sobald das Gerät angelegt ist.') }}
            </p>
        </x-create.abschnitt>

    </x-create.main>
</x-app-layout>
