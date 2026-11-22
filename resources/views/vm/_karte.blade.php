{{-- Eine VM in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $anzahlIps = $adressen->count();
            $primaer = $adressen->first()?->label();
        @endphp

        <x-card :raster="2">
            <x-slot:head>
                <x-show.header can="vm_update" editAction="$dispatch('object-edit', { typ: 'vm', id: {{ $eintrag->id }} })">
                    <span class="shrink-0 font-bold">{{ $eintrag->name }}</span>

                    {{-- VM or container, as the agent reported it: decides how
                         to get in (console vs. pct enter) and what a backup
                         holds. Not stored for entries made by hand. --}}
                    {{-- Symbol und Farbe je Typ: In einer Liste mit beiden
                         Arten waren zwei gleich graue Abzeichen nur am Wort zu
                         unterscheiden, und man las jedes einzeln. Bildschirm
                         und Blau heisst VM, Wuerfel und Violett heisst
                         Container. Das Wort bleibt daneben stehen - Farbe
                         allein traegt die Aussage nicht. --}}
                    @php
                        $gastTyp = match ($eintrag->kind) {
                            'vm' => ['VM', __('Virtuelle Maschine'), 'bg-cerulean-50 text-cerulean-700 ring-cerulean-200 dark:bg-cerulean-400/10 dark:text-cerulean-300 dark:ring-cerulean-400/30'],
                            'lxc' => ['LXC', __('Container (LXC)'), 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-400/10 dark:text-violet-300 dark:ring-violet-400/30'],
                            default => null,
                        };
                    @endphp
                    @if ($gastTyp)
                        <span title="{{ $gastTyp[1] }}"
                            class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $gastTyp[2] }}">
                            @if ($gastTyp[0] === 'VM')
                                {{-- Bildschirm: das, was eine VM mitbringt und ein Container nicht. --}}
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="4" width="18" height="12" rx="2" />
                                    <path stroke-linecap="round" d="M12 16v4m-4 0h8" />
                                </svg>
                            @else
                                {{-- Wuerfel: ein Container, der auf dem Kern des Hosts sitzt. --}}
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linejoin="round" d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z" />
                                    <path stroke-linejoin="round" d="M12 21v-9m0 0l8-4.5M12 12L4 7.5" />
                                </svg>
                            @endif
                            {{ $gastTyp[0] }}
                        </span>
                    @endif

                    {{-- Running or not, as of the last report - the first thing
                         one wants to know about a guest. The dot alone would
                         say it in color only; the word says it to everyone. --}}
                    @php
                        $gastStatus = match ($eintrag->agent_details['status'] ?? null) {
                            'running' => [__('läuft'), 'bg-green-500', 'text-green-700 dark:text-green-400'],
                            'stopped' => [__('gestoppt'), 'bg-gray-400 dark:bg-gray-500', 'text-gray-500 dark:text-gray-400'],
                            'paused' => [__('pausiert'), 'bg-amber-500', 'text-amber-700 dark:text-amber-400'],
                            default => null,
                        };
                    @endphp
                    @if ($gastStatus)
                        <span class="inline-flex items-center gap-1.5 text-sm font-normal {{ $gastStatus[2] }}">
                            <span class="h-2 w-2 rounded-full {{ $gastStatus[1] }}" aria-hidden="true"></span>
                            {{ $gastStatus[0] }}
                        </span>
                    @endif

                    {{-- Operating system and EOL badge in the second line: next
                         to kind and state they were cut ("Windows Server 201…")
                         - and with them the version the badge is about. --}}
                    @if ($eintrag->operatingSystem)
                        <x-slot:untertitel><span class="min-w-0 truncate" title="{{ $eintrag->operatingSystem->name }}">{{ $eintrag->operatingSystem->name }}</span><span class="shrink-0"><x-eol :os="$eintrag->operatingSystem" /></span></x-slot>
                    @endif

                    {{-- Wie beim Server: das Nachgeschlagene in festen Spalten.
                         Statt des Einbauorts steht hier der Host - eine VM
                         steckt in keinem Rack. --}}
                    <x-slot:kernwerte>
                        <x-kernwert :label="__('IP')" :zaehler="max(0, $anzahlIps - 1)" breite="w-36">
                            @if ($primaer)
                                <x-ip-anzeige :adresse="$adressen->first()" />
                            @endif
                        </x-kernwert>

                        {{-- Host oder Cluster - eines von beiden, nie beides.
                             Ohne den Cluster stuende bei einer Cluster-VM
                             nichts, obwohl dokumentiert ist, wo sie laeuft. --}}
                        <x-kernwert :label="$eintrag->cluster && ! $eintrag->host ? __('Cluster') : __('Host')" breite="w-40">{{ $eintrag->host?->name ?? $eintrag->cluster?->name }}</x-kernwert>

                        {{-- As on the server: what runs there, in the head line. --}}
                        <x-dienstchips :dienste="$eintrag->services" class="w-36" />
                    </x-slot>

                    <x-slot:aktionen>
                        <x-remote.button :device="$eintrag" stil="symbol" />
                        <livewire:copy-password-button :device="$eintrag" :key="'kopf-kennwort-vm-'.$eintrag->id" />
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>
                {{-- Zwei feste Spalten (design 1A): Zugang - was man zum Arbeiten
                     braucht; System - Ressourcen von Hand und was der Agent meldet.
                     Dienste stehen seit 26.11.22 im Kopf (x-dienstchips). --}}
                <x-kartenspalte :titel="__('Zugang')" symbol="svg.login">
                    <x-ipcard :device="$eintrag" />

                    <x-credentialscard :device="$eintrag" />

                    {{-- Die Fernwartungs-Kennung auch als Text: Wenn der Knopf nicht greift
                         (anderer Rechner, kein Client), braucht man die ID zum Abtippen. --}}
                    <x-minitablecard :title="__('Fernwartung')" :array="[
                        \App\Models\Setting::remoteAccess()['id_label'] => $eintrag->remoteID,
                        'Passwort' => [$eintrag, 'remotePassword'],
                    ]" />
                </x-kartenspalte>

                @if ($eintrag->agent_reported_at || filled($eintrag->cores) || filled($eintrag->memory_gb) || filled($eintrag->disk_gb))
                    <x-kartenspalte :titel="__('System')" symbol="svg.settings">
                        {{-- By hand, for a guest no agent reports. --}}
                        <x-minitablecard :title="__('Ressourcen')" :array="[
                            'Kerne' => $eintrag->cores,
                            'Arbeitsspeicher' => $eintrag->memory_gb !== null ? \App\Support\AgentReportCards::gb((float) $eintrag->memory_gb) : null,
                            'Festplatte' => $eintrag->disk_gb !== null ? \App\Support\AgentReportCards::gb((float) $eintrag->disk_gb) : null,
                        ]" />

                        <x-agentcard :device="$eintrag" :bloecke="\App\Support\AgentReportCards::vm($eintrag)" />
                    </x-kartenspalte>
                @endif
            </x-slot>

            {{-- Last change, agent, and the links to log, server map, rack. --}}
            <x-slot:fuss>
                <x-kartenfuss :eintrag="$eintrag" />
            </x-slot>
        </x-card>
    
