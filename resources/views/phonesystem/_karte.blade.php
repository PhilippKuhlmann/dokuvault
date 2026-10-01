{{-- Ein Eintrag in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->anzeige();
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="phonesystem_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'phonesystem', id: {{ $eintrag->id }} })">
                    {{-- The name, where there is one - two phone systems of the same
                         make were indistinguishable before. --}}
                    {{ $eintrag->name ?: $eintrag->manufacturer }}

                    @if ($eintrag->name && ($eintrag->manufacturer || $eintrag->model))
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ trim($eintrag->manufacturer.' '.$eintrag->model) }}</span>
                    @endif

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
                    'Modell' => $eintrag->model,
                    'Seriennummer' => $eintrag->serialNumber,
                ]" />

                <x-credentialscard :device="$eintrag" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $eintrag->port,
                ]" />

                <x-beschaffungcard :device="$eintrag" />

            </x-slot>
        </x-card>
    
