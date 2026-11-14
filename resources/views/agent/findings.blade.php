@use('App\Support\LocalTime')
<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-4">

        @include('agent.kopf', ['seite' => 'funde'])

        {{-- One line per finding: what it is, what it is called, where it
             answered. A click opens everything the scan knows about it and
             the three ways to deal with it - the same pattern as the agent
             list, so a dozen printers do not become a dozen forms. --}}
        <x-panel id="neu-gefunden">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-1">
                {{ __('Neu gefunden') }}
                @if ($neuGefunden->isNotEmpty())
                    <span class="ml-1 text-sm font-DINPro text-gray-400 dark:text-gray-500">{{ $neuGefunden->count() }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">
                {{ __('Drucker, USVs, Switches, Accesspoints, NAS und Kameras, die der Netzwerk-Scan gefunden hat. Ein Klick zeigt alles, was der Scan über das Gerät weiß. Übernommen oder verknüpft hält der Scan Hersteller, Modell, Seriennummer, Firmware und Adresse aktuell – Name, Standort und Notizen bleiben deine.') }}
            </p>

            <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse ($neuGefunden as $fund)
                @php
                    $auswahl = $fund->linkChoices();
                    $kandidaten = $fund->linkCandidates();
                @endphp
                <details class="group/fund py-1" @if ($neuGefunden->count() === 1) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 rounded-lg px-2 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/40 [&::-webkit-details-marker]:hidden">
                        <span class="w-24 shrink-0">
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $fund->typeLabel() }}</span>
                        </span>
                        <span class="w-48 min-w-0 shrink-0 truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $fund->suggestedName() }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm text-gray-500 dark:text-gray-400">
                            {{ collect([$fund->manufacturer, $fund->model])->filter()->join(' ') ?: __('Modell unbekannt') }}
                        </span>
                        @if ($kandidaten->isNotEmpty())
                            <span class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ __('schon dokumentiert?') }}</span>
                        @endif
                        <span class="shrink-0 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $fund->ip }}</span>
                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open/fund:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        <span class="sr-only">{{ __('Details zu :name', ['name' => $fund->suggestedName()]) }}</span>
                    </summary>

                    <div class="mx-2 mb-3 mt-1 space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        @include('agent.fund-details', ['fund' => $fund])

                        {{-- Why it may already be documented - by which match. --}}
                        @if ($kandidaten->isNotEmpty())
                            <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                                {{ __('Passt vermutlich zu') }}:
                                {{ $kandidaten->map(fn ($g) => ($g->name ?: '#'.$g->id).' ('.(filled($fund->serial) && $g->serialNumber === $fund->serial ? __('gleiche Seriennummer') : __('gleiche IP-Adresse')).')')->join(', ') }}
                            </div>
                        @endif

                        <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                            <form method="POST" action="{{ route('agent.finding.accept', [$customer, $fund]) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <x-input.field name="name" :value="$fund->suggestedName()" class="w-56 h-9 text-sm" :aria-label="__('Name')" required />
                                <x-input.select name="site_id" class="w-48 h-9 px-3 py-1 text-sm" :aria-label="__('Standort')">
                                    @foreach ($sites as $site)
                                        <option value="{{ $site->id }}" @selected($site->id === $fund->suggestedSiteId())>{{ $site->name }}</option>
                                    @endforeach
                                </x-input.select>
                                <x-input.button type="submit" class="h-9" :label="__('Übernehmen')" />
                            </form>

                            <div class="flex flex-wrap items-center gap-3">
                                @if ($auswahl->isNotEmpty())
                                    <form method="POST" action="{{ route('agent.finding.link', [$customer, $fund]) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <x-input.select name="device_id" class="w-56 h-9 px-3 py-1 text-sm" :aria-label="__('Vorhandenes Gerät')">
                                            @foreach ($auswahl as $geraet)
                                                <option value="{{ $geraet->id }}">{{ $geraet->name ?: '#'.$geraet->id }}{{ $kandidaten->contains('id', $geraet->id) ? ' ★' : '' }}</option>
                                            @endforeach
                                        </x-input.select>
                                        <x-input.button type="submit" color="gray" class="h-9" :label="__('Mit vorhandenem verknüpfen')" />
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('agent.finding.ignore', [$customer, $fund]) }}">
                                    @csrf
                                    <x-input.button type="submit" color="gray" class="h-9" :label="__('Verwerfen')" />
                                </form>
                            </div>
                        </div>
                    </div>
                </details>
            @empty
                @if ($scanAktiv)
                    <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Nichts Neues – alles Gefundene ist dokumentiert.') }}</div>
                @else
                    <div class="text-sm text-gray-400 dark:text-gray-500">{!! __('Noch kein Netzwerk-Scan eingeschaltet. Das geht unter :agenten bei einem Windows-Agenten: Rolle „Netzwerk-Scan (SNMP)“ anhaken und unter „Netzwerk-Scan einstellen“ die Netze wählen.', ['agenten' => '<a href="'.e(route('agent.index', $customer)).'" class="text-cerulean-600 hover:underline dark:text-cerulean-400">'.e(__('Agenten')).'</a>']) !!}</div>
                @endif
            @endforelse
            </div>

            {{-- Ignored devices: never offered again, unless brought back.
                 Each opens like a new one, so one can see what it was. --}}
            @if ($ignoriert->isNotEmpty())
                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer select-none text-gray-500 dark:text-gray-400">{{ __('Ignorierte Geräte') }} ({{ $ignoriert->count() }})</summary>
                    <div class="mt-2 divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($ignoriert as $fund)
                            <details class="group/ignoriert py-1">
                                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 rounded-lg px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700/40 [&::-webkit-details-marker]:hidden">
                                    <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-200">
                                        {{ $fund->suggestedName() }}
                                        <span class="text-xs text-gray-400 dark:text-gray-500">· {{ $fund->typeLabel() }}</span>
                                    </span>
                                    <span class="shrink-0 font-mono text-xs text-gray-400 dark:text-gray-500">{{ $fund->ip }}</span>
                                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open/ignoriert:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                </summary>
                                <div class="mx-2 mb-2 mt-1 space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                    @include('agent.fund-details', ['fund' => $fund])
                                    <form method="POST" action="{{ route('agent.finding.restore', [$customer, $fund]) }}" class="border-t border-gray-200 pt-3 dark:border-gray-700">
                                        @csrf
                                        <x-input.button type="submit" color="gray" size="sm" :label="__('Zurückholen')" />
                                    </form>
                                </div>
                            </details>
                        @endforeach
                    </div>
                </details>
            @endif
        </x-panel>
    </div>
</x-app-layout>
