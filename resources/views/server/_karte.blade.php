{{-- Ein Server in der Liste. Die Karte bleibt beim Typ. --}}
        <x-card>
            @php
                $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
                $anzahlIps = $adressen->count();
                $primaer = $adressen->first()?->label();
            @endphp

            <x-slot:head>
                <x-show.header can="server_update" editAction="$dispatch('object-edit', { typ: 'server', id: {{ $eintrag->id }} })">
                    <span class="shrink-0 font-semibold">{{ $eintrag->name }}</span>

                    {{-- Betriebssystem klein hinter den Namen: Es gehoert zur
                         Identitaet der Maschine, nicht zu den Nachschlagewerten.
                         Cut on wide screens instead of breaking the line. --}}
                    @if ($eintrag->operatingSystem)
                        <span class="min-w-0 truncate text-sm font-normal text-gray-500 dark:text-gray-400" title="{{ $eintrag->operatingSystem->name }}">{{ $eintrag->operatingSystem->name }}</span>
                    @endif

                    {{-- Was man fast immer sucht, in festen Spalten (design 1A):
                         IP, Ort, Dienste stehen in jeder Zeile an derselben
                         Stelle, ein fehlender Wert zeigt einen Strich. Ort: das
                         Rack kurz, sonst der Standort - auch ein Tower steht
                         irgendwo. Der Zaehler verraet, dass mehr da ist. --}}
                    <x-slot:kernwerte>
                        <x-kernwert :label="__('IP')" :zaehler="max(0, $anzahlIps - 1)" breite="w-40">
                            @if ($primaer)
                                <x-ip-anzeige :adresse="$adressen->first()" />
                            @endif
                        </x-kernwert>

                        <x-kernwert :label="__('Ort')" breite="w-44" :titel="$eintrag->mountLocation() ?? $eintrag->site?->name">{{ $eintrag->mountLocationShort() ?? $eintrag->site?->name }}</x-kernwert>

                        {{-- What runs there, in the head line: in a folded card
                             it is what tells one server from the next. --}}
                        <x-dienstchips :dienste="$eintrag->services" class="w-36" />
                    </x-slot>

                    {{-- Fernwartung und Kennwort: die taeglichen Handgriffe,
                         rechts neben dem Stift - ohne die Karte aufzuklappen.
                         Welches Werkzeug dahinter steckt, steht in den
                         Einstellungen. --}}
                    <x-slot:aktionen>
                        <x-remote.button :device="$eintrag" stil="symbol" />
                        <livewire:copy-password-button :device="$eintrag" :key="'kopf-kennwort-server-'.$eintrag->id" />
                    </x-slot>

                    {{-- The EOL badge in the second line: next to the operating
                         system it took the room the version needed - "Windows
                         Server 201…" hid exactly what the badge is about. --}}
                    @php
                        $geraet = implode(' · ', array_filter([
                            trim($eintrag->manufacturer.' '.$eintrag->model),
                            __(config('custom.server_form_factors')[$eintrag->form_factor] ?? ''),
                        ]));
                    @endphp
                    <x-slot:untertitel><span class="min-w-0 truncate" title="{{ $geraet }}">{{ $geraet }}</span>@if ($eintrag->operatingSystem)<span class="shrink-0"><x-eol :os="$eintrag->operatingSystem" /></span>@endif</x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-ipcard :device="$eintrag" />

                <x-credentialscard :device="$eintrag" />

                <x-minitablecard :title="__('BMC')" :modell="$eintrag" :array="[
                    'BMC IP-Adresse' => $eintrag->bmcIp,
                    'BMC Benutzer' => $eintrag->bmcUser,
                    'BMC Passwort' => $eintrag->bmcPassword,
                ]" />

                {{-- Die Fernwartungs-Kennung auch als Text: Wenn der Knopf nicht greift
                     (anderer Rechner, kein Client), braucht man die ID zum Abtippen. --}}
                <x-minitablecard :title="__('Fernwartung')" :array="[
                    \App\Models\Setting::remoteAccess()['id_label'] => $eintrag->remoteID,
                    'Passwort' => [$eintrag, 'remotePassword'],
                ]" />

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

                <x-agentcard :device="$eintrag" :bloecke="\App\Support\AgentReportCards::server($eintrag)" />

                {{-- Betriebssystem steht jetzt in der Kopfzeile - hier waere es doppelt. --}}
                {{-- Dienste stehen seit 26.11.22 im Kopf (x-dienstchips). --}}

                <x-beschaffungcard :device="$eintrag" />

            </x-slot>

            {{-- Last change, agent, and the links to log, server map, rack. --}}
            <x-slot:fuss>
                <x-kartenfuss :eintrag="$eintrag" />
            </x-slot>
        </x-card>
    
