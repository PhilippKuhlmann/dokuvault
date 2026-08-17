<x-app-layout :$customer>
    <x-create.main :header="__('Firewall bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('firewall.update', [$customer, $firewall]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $firewall->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$firewall->name" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" :default="$firewall->manufacturer" />

            <x-create.singlerow :label="__('Modell')" name="model" :default="$firewall->model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$firewall->serialNumber" />

            <x-create.singlerow :label="__('Firmware')" name="firmware" :default="$firewall->firmware" />
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
