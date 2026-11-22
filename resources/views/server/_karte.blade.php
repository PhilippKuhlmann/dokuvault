{{-- Ein Server in der Liste. Die Karte bleibt beim Typ. --}}
        <x-card :columns="$eintrag->agent_reported_at ? 3 : 2">
            @php
                $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
                $anzahlIps = $adressen->count();
                $primaer = $adressen->first()?->label();
            @endphp

            <x-slot:head>
                <x-show.header can="server_update" editAction="$dispatch('object-edit', { typ: 'server', id: {{ $eintrag->id }} })">
                    @php
                        $geraet = implode(' · ', array_filter([
                            trim($eintrag->manufacturer.' '.$eintrag->model),
                            __(config('custom.server_form_factors')[$eintrag->form_factor] ?? ''),
                        ]));
                    @endphp
                    <span class="shrink-0 font-bold">{{ $eintrag->name }}</span>

                    {{-- Betriebssystem klein hinter dem Namen, das EOL-Abzeichen
                         direkt daneben - es gehoert zum System. Zur Not wird
                         das System gekuerzt (voll im Tooltip), das Abzeichen
                         nie. Der Servertyp steht in der zweiten Zeile. --}}
                    @if ($eintrag->operatingSystem)
                        <span class="min-w-0 truncate text-[13px] font-normal text-gray-500 dark:text-gray-400" title="{{ $eintrag->operatingSystem->name }}">{{ $eintrag->operatingSystem->name }}</span>
                        <span class="shrink-0"><x-eol :os="$eintrag->operatingSystem" /></span>
                    @endif

                    {{-- Was man fast immer sucht, in festen Spalten (design 1A):
                         IP, Ort, Dienste stehen in jeder Zeile an derselben
                         Stelle, ein fehlender Wert zeigt einen Strich. Ort: das
                         Rack kurz, sonst der Standort - auch ein Tower steht
                         irgendwo. Der Zaehler verraet, dass mehr da ist. --}}
                    <x-slot:kernwerte>
                        <x-kernwert :label="__('IP')" :zaehler="max(0, $anzahlIps - 1)" width="w-36">
                            @if ($primaer)
                                <x-ip-anzeige :adresse="$adressen->first()" />
                            @endif
                        </x-kernwert>

                        <x-kernwert :label="__('Ort')" width="w-36" :title="$eintrag->mountLocation() ?? $eintrag->site?->name">{{ $eintrag->mountLocationShort() ?? $eintrag->site?->name }}</x-kernwert>

                        {{-- What runs there, in the head line: in a folded card
                             it is what tells one server from the next. --}}
                        <x-service-chips :services="$eintrag->services" class="w-36" />
                    </x-slot>

                    {{-- Fernwartung und Kennwort: die taeglichen Handgriffe,
                         rechts neben dem Stift - ohne die Karte aufzuklappen.
                         Welches Werkzeug dahinter steckt, steht in den
                         Einstellungen. --}}
                    <x-slot:actions>
                        <x-remote.button :device="$eintrag" stil="symbol" />
                        <livewire:copy-password-button :device="$eintrag" :key="'kopf-kennwort-server-'.$eintrag->id" />
                    </x-slot>

                    {{-- Second line: the server type - model and form factor. --}}
                    @if ($geraet !== '')
                        <x-slot:subtitle><span class="min-w-0 truncate" title="{{ $geraet }}">{{ $geraet }}</span></x-slot>
                    @endif
                </x-show.header>
            </x-slot>

            <x-slot:body>
                {{-- Drei feste Spalten nach Thema (design 1A, 26.11.22): Zugang
                     - was man zum Arbeiten braucht; Geraet - was man anfassen
                     kann; System - was der Agent meldet. Ohne Agent zwei. --}}
                <x-card-column :title="__('Zugang')" icon="svg.login">
                    <x-ipcard :device="$eintrag" />

                    <x-credentialscard :device="$eintrag" />

                    <x-minitablecard :title="__('Fernwartung')" :array="[
                    \App\Models\Setting::remoteAccess()['id_label'] => $eintrag->remoteID,
                    'Passwort' => [$eintrag, 'remotePassword'],
                ]" />

                    <x-minitablecard :title="__('BMC')" :modell="$eintrag" :array="[
                    'BMC IP-Adresse' => $eintrag->bmcIp,
                    'BMC Benutzer' => $eintrag->bmcUser,
                    'BMC Passwort' => $eintrag->bmcPassword,
                ]" />
                </x-card-column>

                <x-card-column :title="__('Gerät')" icon="svg.server">
                    <x-minitablecard :title="__('Hardware')" :array="[
                    'Hersteller' => $eintrag->manufacturer,
                    'Modell' => $eintrag->model,
                    'Seriennummer' => $eintrag->serialNumber,
                    'Bauform' => __(config('custom.server_form_factors')[$eintrag->form_factor] ?? ''),
                    'Einbautiefe' => $eintrag->form_factor === 'rack'
                        ? __(config('custom.server_depths')[(int) $eintrag->full_depth] ?? '')
                        : null,
                    'Höheneinheiten' => $eintrag->form_factor === 'rack' ? $eintrag->height_units.' HE' : null,
                    // By hand - what an agent reports stands in its own card.
                    'CPU' => $eintrag->cpu,
                    'Kerne' => $eintrag->cores,
                    'Arbeitsspeicher' => $eintrag->memory_gb !== null ? \App\Support\AgentReportCards::gb((float) $eintrag->memory_gb) : null,
                ]" />

                    <x-beschaffungcard :device="$eintrag" />
                </x-card-column>

                @if ($eintrag->agent_reported_at)
                    <x-card-column :title="__('System')" icon="svg.settings">
                        <x-agentcard :device="$eintrag" :bloecke="\App\Support\AgentReportCards::server($eintrag)" />
                    </x-card-column>
                @endif

            </x-slot>

            {{-- Last change, agent, and the links to log, server map, rack. --}}
            <x-slot:footer>
                <x-card-footer :entry="$eintrag" />
            </x-slot>
        </x-card>
    
