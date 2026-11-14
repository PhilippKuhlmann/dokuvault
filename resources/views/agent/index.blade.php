@use('App\Support\LocalTime')
@use('App\Models\AgentInstallation')
<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-4">

        @include('agent.kopf', ['seite' => 'agenten'])

        {{-- Installed agents, one line each: is it running, what does it do,
             when did it last report. Everything to change sits behind the
             arrow, the actions behind "⋯" - before, every row carried its
             settings, five buttons (two of them red) and five facts at once,
             and "does everything run?" took reading the whole page.

             Defaults follow the detection, except AD - only one DC per domain
             gets it - and the network scan, which is only ever switched on
             by hand. --}}
        <x-panel id="installierte-agenten">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-1">
                {{ __('Installierte Agenten') }}
                @if ($agentenGesamt > 0)
                    <span class="ml-1 text-sm font-DINPro text-gray-400 dark:text-gray-500">{{ $agentenGesamt }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">
                {{ __('Was jeder Agent meldet und wie oft, legst du über den Pfeil fest – gilt ab seinem nächsten Lauf. Agenten mit Problemen stehen oben.') }}
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

            <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse ($installations as $installation)
                @php
                    $rollen = AgentInstallation::availableRoles($installation->kind);
                    $gesperrt = (bool) $installation->agentToken?->isLocked();
                    $still = $installation->isStale();
                    $fehler = $installation->failedRoles();
                    $soll = $aktuelleVersion[$installation->kind] ?? null;
                    $veraltet = $installation->version && $soll && $installation->version !== $soll;
                    $kannScannen = array_key_exists(AgentInstallation::SCAN_ROLE, $rollen);
                    $gewaehlteNetze = $kannScannen ? $installation->scanNetworks() : collect();
                    $offen = session('agentOffen') === $installation->id;
                @endphp
                <div class="flex items-start gap-1 py-1">
                    <details class="group/agent min-w-0 flex-1" @if ($offen) open @endif>
                        {{-- The line itself: a click anywhere opens the settings. --}}
                        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 rounded-lg px-2 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/40 [&::-webkit-details-marker]:hidden">
                            <span @class([
                                    'h-2.5 w-2.5 shrink-0 rounded-full',
                                    'bg-red-500' => $still || $fehler,
                                    'bg-gray-300 dark:bg-gray-600' => ! $still && ! $fehler && $gesperrt,
                                    'bg-green-500' => ! $still && ! $fehler && ! $gesperrt,
                                ])
                                role="img" aria-label="{{ $still || $fehler ? __('Problem') : ($gesperrt ? __('gesperrt') : __('läuft')) }}"></span>

                            <span class="w-44 min-w-0 shrink-0 truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $installation->hostname }}
                                <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ __(config('custom.dienste.'.$installation->kind.'.name', $installation->kind)) }}</span>
                            </span>

                            {{-- What it does, coloured by how its last run went. --}}
                            <span class="flex min-w-0 flex-1 flex-wrap gap-1">
                                @forelse ($installation->roles ?? [] as $rolle)
                                    @php $ergebnis = $installation->resultFor($rolle); @endphp
                                    <span @class([
                                            'whitespace-nowrap rounded-full px-2 py-0.5 text-[11px]',
                                            'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $ergebnis && ! $ergebnis['ok'],
                                            'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300' => $ergebnis && $ergebnis['ok'],
                                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => ! $ergebnis,
                                        ])
                                        title="{{ $ergebnis ? ($ergebnis['ok'] ? __('Letzter Lauf erfolgreich') : __('Letzter Lauf fehlgeschlagen')).' · '.\Illuminate\Support\Carbon::parse($ergebnis['at'])->format('d.m.Y H:i') : __('Noch nicht gelaufen') }}">
                                        {{ \Illuminate\Support\Str::before(__($rollen[$rolle] ?? $rolle), ' (') }}@if ($ergebnis && ! $ergebnis['ok']) ✕@endif
                                    </span>
                                @empty
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('keine Aufgabe') }}</span>
                                @endforelse
                                @if ($gesperrt)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ __('gesperrt') }}</span>
                                @endif
                                @if ($veraltet)
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] text-amber-700 dark:bg-amber-900/30 dark:text-amber-300" title="{{ __('Version') }} {{ $installation->version }}">{{ __('Update ausstehend') }}</span>
                                @endif
                            </span>

                            {{-- When it last reported; exact times in the tooltip. --}}
                            <span @class([
                                    'shrink-0 whitespace-nowrap text-xs tabular-nums',
                                    'font-semibold text-red-600 dark:text-red-400' => $still,
                                    'text-gray-500 dark:text-gray-400' => ! $still,
                                ])
                                title="{{ __('Letzter Lauf') }}: {{ LocalTime::display($installation->last_run_at, 'd.m.Y H:i', __('noch nie')) }} · {{ __('Zuletzt erreichbar') }}: {{ LocalTime::display($installation->last_seen_at, 'd.m.Y H:i', __('noch nie')) }}">
                                @if ($still)
                                    {{ $installation->last_seen_at ? __('meldet nicht seit :zeit', ['zeit' => $installation->last_seen_at->diffForHumans(null, true)]) : __('meldet nicht') }}
                                @else
                                    {{ $installation->last_run_at?->diffForHumans() ?? __('noch nie gelaufen') }}
                                @endif
                            </span>

                            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open/agent:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                            <span class="sr-only">{{ __('Einstellungen von :name', ['name' => $installation->hostname]) }}</span>
                        </summary>

                        {{-- The settings. The interval and "Speichern" belong to
                             the form via the form attribute. --}}
                        <div class="mx-2 mb-3 mt-1 space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                @if ($installation->domain)
                                    {{ $installation->domain }} ·
                                @endif
                                {{ __('Token') }}: {{ $installation->agentToken ? ($installation->agentToken->name ?: 'Token #'.$installation->agentToken->id) : __('widerrufen') }}
                                · {{ __('Version') }}: <span class="font-mono">{{ $installation->version ?? '—' }}</span>
                                @if ($veraltet)
                                    <span class="text-amber-700 dark:text-amber-300">({{ __('aktuell :version – der Agent holt sie sich beim nächsten Lauf', ['version' => $soll]) }})</span>
                                @endif
                            </div>

                            {{-- Failed roles with the end of their output. --}}
                            @foreach ($fehler as $rolle)
                                @php $ergebnis = $installation->resultFor($rolle); @endphp
                                <details class="max-w-2xl text-xs text-red-700 dark:text-red-400" open>
                                    <summary class="cursor-pointer select-none">
                                        {{ __(':rolle fehlgeschlagen', ['rolle' => __($rollen[$rolle] ?? $rolle)]) }}
                                        · {{ \Illuminate\Support\Carbon::parse($ergebnis['at'])->format('d.m.Y H:i') }}
                                    </summary>
                                    <pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap rounded bg-red-50 p-2 text-[11px] text-red-900 dark:bg-red-950/30 dark:text-red-200">{{ $ergebnis['message'] ?? __('Keine Ausgabe.') }}</pre>
                                </details>
                            @endforeach

                            <form id="agent-{{ $installation->id }}" method="POST" action="{{ route('agent.installation.update', [$customer, $installation]) }}">
                                @csrf
                                @method('PUT')
                                <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Aufgaben') }}</div>
                                <div class="flex flex-wrap gap-x-5 gap-y-2">
                                    @foreach ($rollen as $rolle => $bezeichnung)
                                        @php
                                            $an = in_array($rolle, $installation->roles ?? [], true);
                                            $erkannt = in_array($rolle, $installation->detected ?? [], true);
                                        @endphp
                                        <label class="flex cursor-pointer select-none items-center gap-1.5 text-sm text-gray-700 dark:text-gray-200">
                                            <input type="checkbox" name="roles[]" value="{{ $rolle }}" @checked($an)
                                                class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                                            {{ __($bezeichnung) }}
                                            {{-- Only where detection and setting disagree - next to
                                                 every tick "erkannt" said nothing any more. --}}
                                            @if ($erkannt && ! $an)
                                                <span class="text-[10px] text-amber-700 dark:text-amber-300">{{ __('erkannt, aber aus') }}</span>
                                            @elseif ($an && ! $erkannt && $rolle !== AgentInstallation::SCAN_ROLE)
                                                <span class="text-[10px] text-amber-700 dark:text-amber-300">{{ __('nicht erkannt') }}</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                                @if ($kannScannen)
                                    <input type="hidden" name="scan_settings" value="1">
                                @endif
                            </form>

                            {{-- Network scan: the chosen networks in one line, the
                                 choice of all of them only after "ändern". --}}
                            @if ($kannScannen)
                                <div class="space-y-2">
                                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Netzwerk-Scan (Drucker, USV)') }}</div>
                                    <div class="text-sm text-gray-700 dark:text-gray-200">
                                        @if ($gewaehlteNetze->isNotEmpty())
                                            {{ $gewaehlteNetze->map(fn ($n) => AgentInstallation::cidrOf($n))->join(' · ') }}
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">{{ __('Noch kein Netz gewählt.') }}</span>
                                        @endif
                                        @if ($installation->runsScan())
                                            <span class="text-xs text-gray-500 dark:text-gray-400">· {{ __('Letzter Scan') }}: {{ LocalTime::display($installation->last_scan_at, 'd.m.Y H:i', __('noch nie')) }}</span>
                                        @endif
                                    </div>
                                    <details class="text-sm" @if ($offen && $errors->has('scan_communities')) open @endif>
                                        <summary class="cursor-pointer select-none text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('Netze und Communities ändern') }}</summary>
                                        <div class="mt-2 space-y-3">
                                            <p class="max-w-3xl text-xs text-gray-500 dark:text-gray-400">
                                                {{ __('Einmal am Tag fragt der Agent jede Adresse der gewählten Netze per SNMP (UDP 161) ab und meldet Drucker und USVs. Netze hinter einem Router gehen auch, wenn die Firewall dazwischen SNMP durchlässt. Manche EDR- oder IDS-Produkte melden einen solchen Scan als Angriff – vorher Bescheid geben.') }}
                                            </p>
                                            <div>
                                                <div class="mb-1 text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('Netze (höchstens /:praefix)', ['praefix' => AgentInstallation::SCAN_MIN_PREFIX]) }}</div>
                                                @forelse ($scanNetze as $netz)
                                                    <label class="mr-4 inline-flex cursor-pointer select-none items-center gap-1.5 text-sm text-gray-700 dark:text-gray-200">
                                                        <input type="checkbox" name="scan_network_ids[]" value="{{ $netz->id }}" form="agent-{{ $installation->id }}"
                                                            @checked(in_array($netz->id, array_map('intval', $installation->scan_network_ids ?? []), true))
                                                            class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                                                        {{ AgentInstallation::cidrOf($netz) }}
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
                                                    :placeholder="AgentInstallation::SCAN_DEFAULT_COMMUNITY" autocomplete="off" />
                                                <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('Mit Komma getrennt, höchstens :anzahl. Leer: public.', ['anzahl' => AgentInstallation::SCAN_MAX_COMMUNITIES]) }}</div>
                                                @if ($offen)
                                                    <x-input.fehler feld="scan_communities" />
                                                @endif
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                                <x-input.select name="interval_minutes" form="agent-{{ $installation->id }}" class="w-48 h-9 px-3 py-1 text-sm" :aria-label="__('Intervall')">
                                    @foreach (config('custom.agent_intervalle') as $minuten => $bezeichnung)
                                        <option value="{{ $minuten }}" @selected($minuten === $installation->intervalMinutes())>{{ __($bezeichnung) }}</option>
                                    @endforeach
                                </x-input.select>
                                <x-input.button type="submit" form="agent-{{ $installation->id }}" class="h-9" :label="__('Speichern')" />
                            </div>
                        </div>
                    </details>

                    {{-- The actions: report or scan now, lock, remove. Out of the
                         line - two red buttons in every row read as an alarm
                         even when everything was green. --}}
                    <details class="relative mt-1 shrink-0" data-menu>
                        <summary class="flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 [&::-webkit-details-marker]:hidden"
                            aria-label="{{ __('Aktionen für :name', ['name' => $installation->hostname]) }}">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6" /><circle cx="10" cy="10" r="1.6" /><circle cx="16" cy="10" r="1.6" /></svg>
                        </summary>
                        <div class="absolute right-0 z-20 mt-1 w-48 rounded-lg border border-gray-200 bg-white p-1 text-sm shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            {{-- Picked up on the next checkin, within five minutes. --}}
                            @if ($installation->run_requested_at)
                                <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Lauf angefordert') }}</div>
                            @else
                                <form method="POST" action="{{ route('agent.installation.run', [$customer, $installation]) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">{{ __('Jetzt melden') }}</button>
                                </form>
                            @endif
                            @if ($installation->runsScan())
                                @if ($installation->scan_requested_at)
                                    <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Scan angefordert') }}</div>
                                @else
                                    <form method="POST" action="{{ route('agent.installation.scan', [$customer, $installation]) }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">{{ __('Jetzt scannen') }}</button>
                                    </form>
                                @endif
                            @endif
                            @if ($installation->agentToken)
                                <form method="POST" action="{{ route($gesperrt ? 'agent.unlock' : 'agent.lock', [$customer, $installation->agentToken]) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">{{ $gesperrt ? __('Entsperren') : __('Sperren') }}</button>
                                </form>
                            @endif
                            <x-loeschdialog :url="route('agent.installation.destroy', [$customer, $installation])"
                                :frage="__('Agent aus der Liste entfernen?')"
                                :hinweis="__('Nur der Eintrag verschwindet. Ist der Agent noch installiert, meldet er sich beim nächsten Lauf wieder – deinstalliert wird auf dem Rechner.')"
                                :bestaetigen="__('Entfernen')">
                                <x-slot:ausloeser>
                                    <button type="button" x-on:click="offen = true" class="w-full rounded-md px-3 py-2 text-left text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30">{{ __('Entfernen …') }}</button>
                                </x-slot:ausloeser>
                            </x-loeschdialog>
                        </div>
                    </details>
                </div>
            @empty
                @if ($suche !== '')
                    <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Kein Agent passt zu „:suche“.', ['suche' => $suche]) }}</div>
                @else
                    <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Noch kein Agent installiert. Agenten erscheinen hier nach ihrer ersten Meldung.') }}</div>
                @endif
            @endforelse
            </div>

            @if ($installations->hasPages())
                <div class="mt-4">{{ $installations->links() }}</div>
            @endif
        </x-panel>

    </div>
</x-app-layout>
