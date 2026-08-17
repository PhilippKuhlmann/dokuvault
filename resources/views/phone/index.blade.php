<x-app-layout :$customer>

    <x-sitetopmenu can="phone_create" />

    @forelse ($phones as $phone)

        @php
            $adressen = $phone->relationLoaded('ipAddresses') ? $phone->ipAddresses : $phone->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="phone_update" editUrl="{{ route('phone.edit', [$customer, $phone]) }}">
                    Nebenstelle: {{ $phone->extension }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$phone" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $phone->manufacturer,
                    'Modell' => $phone->model,
                    'Seriennummer' => $phone->serialNumber,
                ]" />

                <x-credentialscard :device="$phone" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $phone->port,
                    'MAC-Adresse' => $phone->mac,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $phone->username,
                    'Passwort' => $phone->password,
                ]" />

                <x-beschaffungcard :device="$phone" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $phones->links() }}
    </div>

</x-app-layout>
