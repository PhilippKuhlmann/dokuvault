{{-- Eine VM in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $anzahlIps = $adressen->count();
            $primaer = $adressen->first()?->anzeige();
        @endphp

        <x-card>
            <x-slot:head>
                <x-show.header can="vm_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'vm', id: {{ $eintrag->id }} })">
                    {{-- Die Fernwartung bleibt der erste Knopf in der Kopfzeile: taeglich
                         benutzt. Welches Werkzeug dahinter steckt, steht in den
                         Einstellungen. --}}
                    <x-remote.button :device="$eintrag" />
                    {{ $eintrag->name }}

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
                        $gastTyp = match ($eintrag->agent_details['type'] ?? null) {
                            'qemu' => ['VM', __('Virtuelle Maschine'), 'bg-cerulean-50 text-cerulean-700 ring-cerulean-200 dark:bg-cerulean-400/10 dark:text-cerulean-300 dark:ring-cerulean-400/30'],
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

                    @if ($eintrag->operatingSystem)
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ $eintrag->operatingSystem->name }}</span>

                        <x-eol :os="$eintrag->operatingSystem" />
                    @endif

                    {{-- Wie beim Server: das Nachgeschlagene neben den Namen. Statt des
                         Einbauorts steht hier der Host - eine VM steckt in keinem Rack. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-ip-anzeige :adresse="$adressen->first()" />
                            </x-kernwert>
                        @endif

                        {{-- Host oder Cluster - eines von beiden, nie beides.
                             Ohne den Cluster stuende bei einer Cluster-VM
                             nichts, obwohl dokumentiert ist, wo sie laeuft. --}}
                        @if ($eintrag->host)
                            <x-kernwert :label="__('Host')">{{ $eintrag->host->name }}</x-kernwert>
                        @elseif ($eintrag->cluster)
                            <x-kernwert :label="__('Cluster')">{{ $eintrag->cluster->name }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-ipcard :device="$eintrag" />

                <x-credentialscard :device="$eintrag" />

                {{-- Die Fernwartungs-Kennung auch als Text: Wenn der Knopf nicht greift
                     (anderer Rechner, kein Client), braucht man die ID zum Abtippen. --}}
                <x-minitablecard :title="__('Fernwartung')" :array="[
                    \App\Models\Setting::fernwartung()['id_label'] => $eintrag->remoteID,
                    'Passwort' => [$eintrag, 'remotePassword'],
                ]" />

                <x-agentcard :device="$eintrag" :bloecke="\App\Support\AgentAnzeige::vm($eintrag)" />

                <x-minitagcard :title="__('Dienste')" :array="$eintrag->services" />

            </x-slot>
        </x-card>
    
