{{-- Ein Eintrag in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->anzeige();
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="nas_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'nas', id: {{ $eintrag->id }} })" >
                    {{ $eintrag->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-ip-anzeige :adresse="$adressen->first()" />
                            </x-kernwert>
                        @endif

                        @if ($eintrag->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $eintrag->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$eintrag" />

                <x-minitablecard :title="__('Hardware')" :array="[
                    'Hersteller' => $eintrag->manufacturer,
                    'Modell' => $eintrag->model,
                    'Seriennummer' => $eintrag->serialNumber,
                ]" />

                <x-credentialscard :device="$eintrag" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $eintrag->port,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $eintrag->username,
                    'Passwort' => $eintrag->password,
                ]" />

                <x-beschaffungcard :device="$eintrag" />

            </x-slot>
        </x-card>
    
