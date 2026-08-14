<x-app-layout :$customer>

    <x-sitetopmenu can="securepointuma_create" />

    @forelse ($customer->securepointumas as $securepointuma)

        @php
            $adressen = $securepointuma->relationLoaded('ipAddresses') ? $securepointuma->ipAddresses : $securepointuma->ipAddresses()->get();
            $primaer = $securepointuma->ip1 ?? $securepointuma->ip;
            $anzahlIps = collect([$primaer, $securepointuma->ip2 ?? null])->filter()->count() + $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="securepointuma_update" editUrl="{{ route('securepointuma.edit', [$customer, $securepointuma]) }}">
                    {{ $securepointuma->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($securepointuma->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $securepointuma->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$securepointuma" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller / Produkt' => $securepointuma->manufacturer,
                    'Art' => $securepointuma->type,
                ]" />

                <x-credentialscard :device="$securepointuma" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $securepointuma->username,
                    'Passwort' => $securepointuma->password,
                    'Verschlüsselungscode' => $securepointuma->encryptionkey,
                ]" />

                <x-minitablecard :title="__('URL')" :array="[
                    'Admin URL' => $securepointuma->urlAdmin,
                    'User URL' => $securepointuma->urlUser,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

</x-app-layout>
