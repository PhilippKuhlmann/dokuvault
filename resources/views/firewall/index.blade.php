<x-app-layout :$customer>

    <x-sitetopmenu can="firewall_create" />

    @forelse ($firewalls as $firewall)

        @php
            $adressen = $firewall->relationLoaded('ipAddresses') ? $firewall->ipAddresses : $firewall->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="firewall_update" editUrl="{{ route('firewall.edit', [$customer, $firewall]) }}">
                    {{ $firewall->name }}

                    {{-- Was man an einer Firewall fast immer sucht: die Adresse,
                         der Einbauort und ob die Subscription noch laeuft. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($firewall->firmware)
                            <x-kernwert :label="__('Firmware')">{{ $firewall->firmware }}</x-kernwert>
                        @endif

                        @if ($firewall->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $firewall->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-ipcard :device="$firewall" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $firewall->manufacturer,
                    'Modell' => $firewall->model,
                    'Seriennummer' => $firewall->serialNumber,
                    'Firmware' => $firewall->firmware,
                ]" />

                <x-credentialscard :device="$firewall" />

                <x-minitablecard :title="__('Zugang')" :array="[
                    'Oberfläche' => $firewall->management_url,
                    'Benutzername' => $firewall->username,
                    'Passwort' => $firewall->password,
                    'Port' => $firewall->port,
                ]" />

                <x-minitablecard :title="__('Subscription')" :array="[
                    'Läuft bis' => $firewall->subscription_until?->format('d.m.Y'),
                ]" />

                <x-minitablecard :title="__('Notizen')" :array="[
                    'Notizen' => $firewall->notes,
                ]" />

                <x-beschaffungcard :device="$firewall" />

            </x-slot>
        </x-card>
    @empty
        <x-emptystate />
    @endforelse

    <div class="px-3 pb-3">
        {{ $firewalls->links() }}
    </div>

</x-app-layout>
