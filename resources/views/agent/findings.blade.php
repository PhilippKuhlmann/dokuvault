@use('App\Support\LocalTime')
<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-4">

        @include('agent.kopf', ['seite' => 'funde'])

        <x-panel id="neu-gefunden">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-1">
                {{ __('Neu gefunden') }}
                @if ($neuGefunden->isNotEmpty())
                    <span class="ml-1 text-sm font-DINPro text-gray-400 dark:text-gray-500">{{ $neuGefunden->count() }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">
                {{ __('Drucker und USVs, die der Netzwerk-Scan gefunden hat. Übernommen oder verknüpft hält der Scan Hersteller, Modell, Seriennummer, Firmware und Adresse aktuell – Name, Standort und Notizen bleiben deine.') }}
            </p>

            @forelse ($neuGefunden as $fund)
                @php($auswahl = $fund->linkChoices())
                @php($kandidaten = $fund->linkCandidates()->pluck('id'))
                <div class="py-3 border-b border-gray-100 last:border-0 dark:border-gray-700">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $fund->suggestedName() }}
                        <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $fund->typeLabel() }}</span>
                        @if ($kandidaten->isNotEmpty())
                            <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ __('schon dokumentiert?') }}</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ collect([$fund->manufacturer, $fund->model])->filter()->join(' ') ?: __('Modell unbekannt') }} ·
                        {{ __('Seriennummer') }}: {{ $fund->serial ?? '—' }} ·
                        {{ __('Firmware') }}: {{ $fund->firmware ?? '—' }} ·
                        {{ $fund->ip }}@if ($fund->network) ({{ $fund->network->label() }})@endif ·
                        {{ __('Zuletzt gesehen') }}: {{ LocalTime::display($fund->last_seen_at, 'd.m.Y H:i', '—') }}
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-3">
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

                        @if ($auswahl->isNotEmpty())
                            <form method="POST" action="{{ route('agent.finding.link', [$customer, $fund]) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <x-input.select name="device_id" class="w-56 h-9 px-3 py-1 text-sm" :aria-label="__('Vorhandenes Gerät')">
                                    @foreach ($auswahl as $geraet)
                                        <option value="{{ $geraet->id }}">{{ $geraet->name ?: '#'.$geraet->id }}{{ $kandidaten->contains($geraet->id) ? ' ★' : '' }}</option>
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
            @empty
                @if ($scanAktiv)
                    <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Nichts Neues – alles Gefundene ist dokumentiert.') }}</div>
                @else
                    <div class="text-sm text-gray-400 dark:text-gray-500">{!! __('Noch kein Netzwerk-Scan eingeschaltet. Das geht unter :agenten bei einem Windows-Agenten: Rolle „Netzwerk-Scan (Drucker, USV)“ anhaken und unter „Netzwerk-Scan einstellen“ die Netze wählen.', ['agenten' => '<a href="'.e(route('agent.index', $customer)).'" class="text-cerulean-600 hover:underline dark:text-cerulean-400">'.e(__('Agenten')).'</a>']) !!}</div>
                @endif
            @endforelse

            {{-- Ignored devices: never offered again, unless brought back here. --}}
            @if ($ignoriert->isNotEmpty())
                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer select-none text-gray-500 dark:text-gray-400">{{ __('Ignorierte Geräte') }} ({{ $ignoriert->count() }})</summary>
                    <div class="mt-2 divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($ignoriert as $fund)
                            <div class="flex flex-wrap items-center justify-between gap-3 py-2">
                                <span class="text-gray-700 dark:text-gray-200">
                                    {{ $fund->suggestedName() }}
                                    <span class="text-xs text-gray-400 dark:text-gray-500">· {{ $fund->typeLabel() }} · {{ $fund->serial ?? $fund->identifier }} · {{ $fund->ip }}</span>
                                </span>
                                <form method="POST" action="{{ route('agent.finding.restore', [$customer, $fund]) }}">
                                    @csrf
                                    <x-input.button type="submit" color="gray" size="sm" :label="__('Zurückholen')" />
                                </form>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </x-panel>
    </div>
</x-app-layout>
