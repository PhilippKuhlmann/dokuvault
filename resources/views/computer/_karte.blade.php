{{-- Ein Eintrag in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->anzeige();
            $anzahlIps = $adressen->count();
        @endphp
    <x-card>
        <x-slot:head>
            <x-show.header can="computer_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'computer', id: {{ $eintrag->id }} })">
                @if ($eintrag->remoteID AND $eintrag->remotePassword)
                    <x-remote.button :device="$eintrag" stil="text" />
                @endif
                {{ $eintrag->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-ip-anzeige :adresse="$adressen->first()" />
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

            <x-minitextcard :title="__('Betriebssystem')">
                {{ $eintrag->operatingSystem?->name ?? '—' }}
                <x-eol :os="$eintrag->operatingSystem" />
            </x-minitextcard>

            <x-beschaffungcard :device="$eintrag" />

        </x-slot>
    </x-card>
