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
                    @php
                        $gastTyp = match ($eintrag->agent_details['type'] ?? null) {
                            'qemu' => ['VM', __('Virtuelle Maschine')],
                            'lxc' => ['LXC', __('Container (LXC)')],
                            default => null,
                        };
                    @endphp
                    @if ($gastTyp)
                        <span title="{{ $gastTyp[1] }}" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $gastTyp[0] }}</span>
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
    
