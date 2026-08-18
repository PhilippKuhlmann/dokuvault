{{-- Ein Eintrag in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="accesspoint_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'accesspoint', id: {{ $eintrag->id }} })">
                    {{ $eintrag->name }}

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


                <x-ipcard :device="$eintrag" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $eintrag->manufacturer,
                    'Modell' => $eintrag->model,
                    'Seriennummer' => $eintrag->serialNumber,
                ]" />

                <x-credentialscard :device="$eintrag" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $eintrag->username,
                    'Passwort' => $eintrag->password,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $eintrag->port,
                ]" />

                <x-beschaffungcard :device="$eintrag" />

            </x-slot>
        </x-card>
    
