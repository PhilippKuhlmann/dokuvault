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

                @php
                    $details = $eintrag->agent_details ?? [];
                @endphp
                <x-agentcard :device="$eintrag" :title="__('System')" :werte="[
                    'VMID' => $details['vmid'] ?? null,
                    'Status' => match ($details['status'] ?? null) {
                        'running' => __('läuft'),
                        'stopped' => __('gestoppt'),
                        'paused' => __('pausiert'),
                        default => $details['status'] ?? null,
                    },
                    __('Kerne') => $details['cores'] ?? null,
                    'Arbeitsspeicher' => isset($details['memory_gb']) ? $details['memory_gb'].' GB' : null,
                ]" />

                <x-minitagcard :title="__('Dienste')" :array="$eintrag->services" />

            </x-slot>
        </x-card>
    
