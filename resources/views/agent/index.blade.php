@use('App\Support\LocalTime')
<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-4">

        @include('agent.kopf', ['seite' => 'agenten'])

        {{-- Installed agents: each reports in before every run and gets the
             roles ticked here. Defaults follow the detection, except AD -
             only one DC per domain gets it, the domain is the same from
             every DC. --}}
        <x-panel id="installierte-agenten">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-1">
                {{ __('Installierte Agenten') }}
                @if ($agentenGesamt > 0)
                    <span class="ml-1 text-sm font-DINPro text-gray-400 dark:text-gray-500">{{ $agentenGesamt }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">
                {{ __('Was jeder Agent meldet und wie oft, legst du hier fest – gilt ab seinem nächsten Lauf. „erkannt“ heißt: der Rechner hat die Rolle. Active Directory reicht von einem Domänencontroller je Domäne.') }}
            </p>

            {{-- Search only once the list has more than one page - with a
                 handful of agents it would be noise. --}}
            @if ($agentenGesamt > $installations->perPage() || $suche !== '')
                <form method="GET" action="{{ route('agent.index', $customer) }}#installierte-agenten" class="mb-3 flex flex-wrap items-center gap-2">
                    <x-input.field type="search" name="suche" :value="$suche" class="w-64" :placeholder="__('Rechner oder Domäne suchen')" :aria-label="__('Rechner oder Domäne suchen')" />
                    <x-input.button type="submit" color="gray" class="h-9" :label="__('Suchen')" />
                    @if ($suche !== '')
                        <a href="{{ route('agent.index', $customer) }}#installierte-agenten" class="text-sm text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('zurücksetzen') }}</a>
                    @endif
                </form>
            @endif

            @forelse ($installations as $installation)
                @php($rollen = \App\Models\AgentInstallation::availableRoles($installation->kind))
                <div class="flex flex-wrap items-start justify-between gap-3 py-3 border-b border-gray-100 last:border-0 dark:border-gray-700">
                    <div class="min-w-48">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $installation->hostname }}
                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ __(config('custom.dienste.'.$installation->kind.'.name', $installation->kind)) }}</span>
                            @if ($installation->isStale())
                                <span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('meldet nicht') }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($installation->domain)
                                {{ $installation->domain }} ·
                            @endif
                            {{ __('Letzter Lauf') }}: {{ LocalTime::display($installation->last_run_at, 'd.m.Y H:i', __('noch nie')) }} ·
                            {{ __('Zuletzt erreichbar') }}: {{ LocalTime::display($installation->last_seen_at, 'd.m.Y H:i', __('noch nie')) }} ·
                            {{ __('Version') }}: {{ $installation->version ?? '—' }} ·
                            {{ __('Token') }}: {{ $installation->agentToken ? ($installation->agentToken->name ?: 'Token #'.$installation->agentToken->id) : __('widerrufen') }}
                            @if ($installation->agentToken?->isLocked())
                                <span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('gesperrt') }}</span>
                            @endif
                        </div>
                        {{-- Failed roles with the end of their output - visible
                             without hovering, the full text folded. --}}
                        @foreach ($installation->failedRoles() as $rolle)
                            @php($ergebnis = $installation->resultFor($rolle))
                            <details class="mt-1 max-w-xl text-xs text-red-700 dark:text-red-400">
                                <summary class="cursor-pointer select-none">
                                    {{ __(':rolle fehlgeschlagen', ['rolle' => __($rollen[$rolle] ?? $rolle)]) }}
                                    · {{ \Illuminate\Support\Carbon::parse($ergebnis['at'])->format('d.m.Y H:i') }}
                                </summary>
                                <pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap rounded bg-red-50 p-2 text-[11px] text-red-900 dark:bg-red-950/30 dark:text-red-200">{{ $ergebnis['message'] ?? __('Keine Ausgabe.') }}</pre>
                            </details>
                        @endforeach
                    </div>

                    {{-- Two rows: the roles, then every control in one line. With
                         six Windows roles in the same row the buttons broke
                         apart - "Speichern" alone, the other two below. The
                         interval and "Speichern" belong to the form via the
                         form attribute. --}}
                    <div class="w-full space-y-3">
                        <form id="agent-{{ $installation->id }}" method="POST" action="{{ route('agent.installation.update', [$customer, $installation]) }}" class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            @csrf
                            @method('PUT')
                            @foreach ($rollen as $rolle => $bezeichnung)
                                <label class="flex cursor-pointer select-none items-center gap-1.5 text-sm text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" name="roles[]" value="{{ $rolle }}" @checked(in_array($rolle, $installation->roles ?? [], true))
                                        class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                                    {{ __($bezeichnung) }}
                                    {{-- Outcome of the last run of this role. --}}
                                    @if ($ergebnis = $installation->resultFor($rolle))
                                        <span @class([
                                                'inline-block h-2 w-2 rounded-full',
                                                'bg-green-500' => $ergebnis['ok'],
                                                'bg-red-500' => ! $ergebnis['ok'],
                                            ])
                                            title="{{ ($ergebnis['ok'] ? __('Letzter Lauf erfolgreich') : __('Letzter Lauf fehlgeschlagen')).' · '.\Illuminate\Support\Carbon::parse($ergebnis['at'])->format('d.m.Y H:i') }}"></span>
                                    @endif
                                    @if (in_array($rolle, $installation->detected ?? [], true))
                                        <span class="text-[10px] text-green-700 dark:text-green-400">{{ __('erkannt') }}</span>
                                    @endif
                                </label>
                            @endforeach
                            @if (array_key_exists(\App\Models\AgentInstallation::SCAN_ROLE, $rollen))
                                <input type="hidden" name="scan_settings" value="1">
                            @endif
                        </form>
                        {{-- Network scan: which documented networks, which SNMP
                             communities. Folded - most agents never scan. --}}
                        @if (array_key_exists(\App\Models\AgentInstallation::SCAN_ROLE, $rollen))
                            <details class="text-sm" @if ($installation->runsScan() || $errors->has('scan_communities')) open @endif>
                                <summary class="cursor-pointer select-none text-gray-500 dark:text-gray-400">
                                    {{ __('Netzwerk-Scan einstellen') }}
                                    @if ($installation->runsScan())
                                        · {{ __('Letzter Scan') }}: {{ LocalTime::display($installation->last_scan_at, 'd.m.Y H:i', __('noch nie')) }}
                                    @endif
                                </summary>
                                <div class="mt-2 space-y-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('Einmal am Tag fragt der Agent jede Adresse der gewählten Netze per SNMP (UDP 161) ab und meldet Drucker und USVs. Netze hinter einem Router gehen auch, wenn die Firewall dazwischen SNMP durchlässt. Manche EDR- oder IDS-Produkte melden einen solchen Scan als Angriff – vorher Bescheid geben.') }}
                                    </p>
                                    <div>
                                        <div class="text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">{{ __('Netze (höchstens /:praefix)', ['praefix' => \App\Models\AgentInstallation::SCAN_MIN_PREFIX]) }}</div>
                                        @forelse ($scanNetze as $netz)
                                            <label class="mr-4 inline-flex cursor-pointer select-none items-center gap-1.5 text-sm text-gray-700 dark:text-gray-200">
                                                <input type="checkbox" name="scan_network_ids[]" value="{{ $netz->id }}" form="agent-{{ $installation->id }}"
                                                    @checked(in_array($netz->id, array_map('intval', $installation->scan_network_ids ?? []), true))
                                                    class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                                                {{ \App\Models\AgentInstallation::cidrOf($netz) }}
                                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ collect([$netz->label(true), $netz->site?->name])->filter()->join(' · ') }}</span>
                                            </label>
                                        @empty
                                            <div class="text-xs text-gray-400 dark:text-gray-500">{{ __('Keine passenden Netze dokumentiert – der Scan braucht ein IPv4-Netz mit Präfix (z. B. /24).') }}</div>
                                        @endforelse
                                    </div>
                                    <div>
                                        <x-input.label for="scan-communities-{{ $installation->id }}" :value="__('SNMP-Communities')" />
                                        <x-input.field id="scan-communities-{{ $installation->id }}" name="scan_communities" form="agent-{{ $installation->id }}"
                                            :value="implode(', ', $installation->scan_communities ?? [])" class="mt-1 w-full max-w-md h-9 text-sm"
                                            :placeholder="\App\Models\AgentInstallation::SCAN_DEFAULT_COMMUNITY" autocomplete="off" />
                                        <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('Mit Komma getrennt, höchstens :anzahl. Leer: public.', ['anzahl' => \App\Models\AgentInstallation::SCAN_MAX_COMMUNITIES]) }}</div>
                                        <x-input.fehler feld="scan_communities" />
                                    </div>
                                </div>
                            </details>
                        @endif
                        <div class="flex flex-wrap items-center gap-3">
                            {{-- One height for all controls of the row (h-9): the select came in
                                 its form size, the buttons in their small one. --}}
                            <x-input.select name="interval_minutes" form="agent-{{ $installation->id }}" class="w-48 h-9 px-3 py-1 text-sm" :aria-label="__('Intervall')">
                                @foreach (config('custom.agent_intervalle') as $minuten => $bezeichnung)
                                    <option value="{{ $minuten }}" @selected($minuten === $installation->intervalMinutes())>{{ __($bezeichnung) }}</option>
                                @endforeach
                            </x-input.select>
                            <x-input.button type="submit" form="agent-{{ $installation->id }}" class="h-9" :label="__('Speichern')" />
                        @if ($installation->agentToken)
                            <form method="POST" action="{{ route($installation->agentToken->isLocked() ? 'agent.unlock' : 'agent.lock', [$customer, $installation->agentToken]) }}">
                                @csrf
                                <x-input.button type="submit" :color="$installation->agentToken->isLocked() ? 'gray' : 'red'" class="h-9" :label="$installation->agentToken->isLocked() ? __('Entsperren') : __('Sperren')" />
                            </form>
                        @endif
                        @if ($installation->runsScan())
                            <form method="POST" action="{{ route('agent.installation.scan', [$customer, $installation]) }}">
                                @csrf
                                @if ($installation->scan_requested_at)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Scan angefordert') }}</span>
                                @else
                                    <x-input.button type="submit" color="gray" class="h-9" :label="__('Jetzt scannen')" />
                                @endif
                            </form>
                        @endif
                        {{-- Picked up on the next checkin, within five minutes. --}}
                        <form method="POST" action="{{ route('agent.installation.run', [$customer, $installation]) }}">
                            @csrf
                            @if ($installation->run_requested_at)
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lauf angefordert') }}</span>
                            @else
                                <x-input.button type="submit" color="gray" class="h-9" :label="__('Jetzt melden')" />
                            @endif
                        </form>
                        <x-loeschdialog :url="route('agent.installation.destroy', [$customer, $installation])"
                            :frage="__('Agent aus der Liste entfernen?')"
                            :hinweis="__('Nur der Eintrag verschwindet. Ist der Agent noch installiert, meldet er sich beim nächsten Lauf wieder – deinstalliert wird auf dem Rechner.')"
                            :bestaetigen="__('Entfernen')">
                            <x-slot:ausloeser>
                                <x-input.button type="button" color="red" class="h-9"
                                    x-on:click="offen = true" :label="__('Entfernen')" />
                            </x-slot:ausloeser>
                        </x-loeschdialog>
                        </div>
                    </div>
                </div>
            @empty
                @if ($suche !== '')
                    <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Kein Agent passt zu „:suche“.', ['suche' => $suche]) }}</div>
                @else
                    <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Noch kein Agent installiert. Agenten erscheinen hier nach ihrer ersten Meldung.') }}</div>
                @endif
            @endforelse

            @if ($installations->hasPages())
                <div class="mt-4">{{ $installations->links() }}</div>
            @endif
        </x-panel>

    </div>
</x-app-layout>
