<x-app-layout :$customer>
    <x-create.main :header="__('Firewall bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('firewall.update', [$customer, $firewall]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $firewall->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$firewall->name" />
        </x-create.abschnitt>

        {{-- Wie im Anlegen-Formular: Die Securepoint-Felder haengen am
             Herstellernamen, nicht an einem eigenen Geraetetyp. --}}
        <x-create.abschnitt :titel="__('Hardware')"
            x-data="{ hersteller: '{{ old('manufacturer', $firewall->manufacturer) }}' }">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$firewall->manufacturer" x-model="hersteller" />

            <x-create.singlerow :label="__('Modell')" name="model" :default="$firewall->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$firewall->serialNumber" />

            <x-create.singlerow :label="__('Firmware')" name="firmware" :default="$firewall->firmware" />

            <x-create.options :label="__('Bauform')" name="form_factor"
                :options="config('custom.firewall_form_factors')" :default="$firewall->form_factor" />

            <div x-show="hersteller.toLowerCase().includes('securepoint')" x-cloak
                class="grid grid-cols-1 gap-x-4 sm:col-span-2 sm:grid-cols-2">
                <x-create.singlerow :label="__('USC-PIN')" name="usc_pin" :default="$firewall->usc_pin" />

                <x-create.singlerow :label="__('Cloud-Backup-Kennwort')" name="cloud_backup_password" :default="$firewall->cloud_backup_password" />

                <x-create.singlerow :label="__('Benutzerportal')" name="url_user" :default="$firewall->url_user" />

                <x-create.singlerow :label="__('Externer Zugang')" name="url_external" :default="$firewall->url_external" />
            </div>
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Verwaltungsoberfläche')" name="management_url" :default="$firewall->management_url" />

            <x-create.singlerow :label="__('Benutzername')" name="username" :default="$firewall->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$firewall->password" />

            <x-create.singlerow :label="__('Port')" name="port" :default="$firewall->port" type="number" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Subscription')">
            <x-create.singlerow :label="__('Subscription bis')" name="subscription_until" type="date" :default="$firewall->subscription_until?->format('Y-m-d')" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Sonstiges')">
            <x-create.singlerow :label="__('Notizen')" name="notes" :default="$firewall->notes" />
        </x-create.abschnitt>

        <x-create.beschaffung :model="$firewall" />

        <x-slot:nach>
            <livewire:device-ip-addresses :model="$firewall" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$firewall" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('firewall_delete')
        <x-deletecard action="{{ route('firewall.destroy', [$customer, $firewall]) }}" breit />
    @endcan

</x-app-layout>
