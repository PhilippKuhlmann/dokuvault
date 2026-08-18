{{-- Ein Eintrag in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
    <x-card>
        <x-slot:head>
            <x-show.header can="ups_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'ups', id: {{ $eintrag->id }} })">
                {{ $eintrag->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
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
            <x-minitablecard :title="__('Allgemein')" :array="[
                'Hersteller' => $eintrag->manufacturer,
                'Modell' => $eintrag->model,
                'Seriennummer' => $eintrag->serialNumber,
            ]" />

            <x-credentialscard :device="$eintrag" />
            <x-minitablecard :title="__('Technik')" :array="[
                'Kapazität' => $eintrag->capacity,
                'Laufzeit' => $eintrag->runtime,
            ]" />
            @if ($eintrag->notes)
                <x-minitextcard :title="__('Notizen')">{{ $eintrag->notes }}</x-minitextcard>
            @endif

            <x-beschaffungcard :device="$eintrag" />

        </x-slot>
    </x-card>
    
