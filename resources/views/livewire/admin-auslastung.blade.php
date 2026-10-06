<div class="p-3 sm:p-5 space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('API-Auslastung') }}</div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Anfragen an die Schnittstelle – von Agenten und API-Token. Steigt die Zahl oder werden die Antworten langsamer, braucht der Server mehr Leistung.') }}
            </p>
        </div>
        <div class="flex gap-1">
            @foreach ($zeitraeume as $wert => $beschriftung)
                <button type="button" wire:click="$set('zeitraum', '{{ $wert }}')" @class([
                    'rounded-lg px-3 py-1.5 text-sm transition-colors',
                    'bg-cerulean-600 text-white' => $zeitraum === $wert,
                    'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' => $zeitraum !== $wert,
                ])>{{ __($beschriftung) }}</button>
            @endforeach
        </div>
    </div>

    {{-- Key figures. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach ([
            [__('Anfragen'), number_format($summe['anzahl'], 0, ',', '.'), __(':agent von Agenten, :api per API-Token', ['agent' => number_format($summe['agent'], 0, ',', '.'), 'api' => number_format($summe['api'], 0, ',', '.')])],
            [__('Spitze je Stunde'), number_format($summe['spitze'], 0, ',', '.'), $summe['spitze_wann'] ? $summe['spitze_wann']->format('d.m. H:00').' '.__('Uhr') : '—'],
            [__('Antwortzeit Ø'), $summe['dauer_schnitt'].' ms', __('über alle Anfragen')],
            [__('Antwortzeit max.'), $summe['dauer_max'].' ms', __('langsamste Anfrage')],
            [__('Fehler (5xx)'), number_format($summe['fehler'], 0, ',', '.'), __(':agenten installierte Agenten', ['agenten' => $agenten])],
        ] as [$titel, $wert, $zusatz])
            <x-panel polster="eng">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $titel }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $wert }}</div>
                <div class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $zusatz }}">{{ $zusatz }}</div>
            </x-panel>
        @endforeach
    </div>

    @php
        $maxVerlauf = max(1, collect($verlauf)->max('anzahl'));
        $maxProfil = max(1, collect($tagesprofil)->max('schnitt'));
    @endphp

    {{-- Over time: per hour for 24 hours, per day for longer periods. --}}
    <x-panel>
        <div class="mb-3 text-lg font-CoconPro text-gray-900 dark:text-gray-100">
            {{ $zeitraum === '24h' ? __('Anfragen je Stunde') : __('Anfragen je Tag') }}
        </div>
        <div class="flex h-40 items-end gap-0.5">
            {{-- The column grows from the bottom (flex items-end); the tooltip
                 component carries the height - inside its inline-block a bar
                 hung from the top. --}}
            @foreach ($verlauf as $b)
                <div class="flex h-full min-w-0 flex-1 items-end">
                    <x-hovertext dunkel class="block! w-full"
                        style="height: {{ $b['anzahl'] ? max(2, round($b['anzahl'] / $maxVerlauf * 100)) : 0 }}%"
                        :text="$b['titel'].' · '.number_format($b['anzahl'], 0, ',', '.').' '.__('Anfragen').($b['anzahl'] ? ' · Ø '.$b['dauer'].' ms' : '')">
                        <span tabindex="0" class="block h-full w-full rounded-t bg-cerulean-500 focus:outline-hidden focus:ring-2 focus:ring-cerulean-300 dark:bg-cerulean-600"></span>
                    </x-hovertext>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex gap-0.5 text-[10px] text-gray-400 dark:text-gray-500">
            @foreach ($verlauf as $i => $b)
                {{-- Every label would overlap at 30 bars; every few is enough. --}}
                <span class="min-w-0 flex-1 truncate text-center">{{ $i % (count($verlauf) > 12 ? 3 : 1) === 0 ? $b['beschriftung'] : '' }}</span>
            @endforeach
        </div>
    </x-panel>

    {{-- When in the day: average per hour of day. --}}
    <x-panel>
        <div class="mb-1 text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Tagesprofil') }}</div>
        <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Durchschnittliche Anfragen je Uhrzeit im gewählten Zeitraum – zeigt, wann die Last kommt.') }}</p>
        <div class="flex h-32 items-end gap-0.5">
            @foreach ($tagesprofil as $p)
                <div class="flex h-full min-w-0 flex-1 items-end">
                    <x-hovertext dunkel class="block! w-full"
                        style="height: {{ $p['schnitt'] ? max(2, round($p['schnitt'] / $maxProfil * 100)) : 0 }}%"
                        :text="sprintf('%02d:00–%02d:59', $p['stunde'], $p['stunde']).' · Ø '.number_format($p['schnitt'], 0, ',', '.').' '.__('Anfragen')">
                        <span tabindex="0" class="block h-full w-full rounded-t bg-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-300 dark:bg-emerald-600"></span>
                    </x-hovertext>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex gap-0.5 text-[10px] text-gray-400 dark:text-gray-500">
            @foreach ($tagesprofil as $p)
                <span class="min-w-0 flex-1 text-center">{{ $p['stunde'] % 3 === 0 ? $p['stunde'] : '' }}</span>
            @endforeach
        </div>
    </x-panel>

    {{-- Per endpoint: which agent causes the load, and which answers slowly. --}}
    <x-panel polster="keins" class="overflow-x-auto">
        <table class="w-full min-w-160 text-left text-sm text-gray-500 dark:text-gray-400">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Endpunkt') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Art') }}</th>
                    <th class="px-4 py-2.5 text-right font-semibold">{{ __('Anfragen') }}</th>
                    <th class="px-4 py-2.5 text-right font-semibold">{{ __('Ø ms') }}</th>
                    <th class="px-4 py-2.5 text-right font-semibold">{{ __('max. ms') }}</th>
                    <th class="px-4 py-2.5 text-right font-semibold">{{ __('Fehler') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($endpunkte as $e)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700">
                        <td class="px-4 py-2 font-mono text-xs text-gray-900 dark:text-gray-100">{{ $e['pfad'] }}</td>
                        <td class="px-4 py-2">{{ $e['art'] === 'agent' ? __('Agent') : __('API-Token') }}</td>
                        <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">{{ number_format($e['anzahl'], 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ $e['dauer_schnitt'] }}</td>
                        <td class="px-4 py-2 text-right">{{ $e['dauer_max'] }}</td>
                        <td @class(['px-4 py-2 text-right', 'font-medium text-red-600 dark:text-red-400' => $e['fehler'] > 0])>{{ $e['fehler'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Im gewählten Zeitraum gab es keine Anfragen.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-panel>
</div>
