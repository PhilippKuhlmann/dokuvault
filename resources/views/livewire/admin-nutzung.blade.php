@use('App\Support\Zeit')
<div class="p-3 sm:p-5 space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Nutzung') }}</div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Anmeldungen und Änderungen aus dem Protokoll – so weit zurück, wie das Protokoll Einträge aufbewahrt.') }}</p>
        </div>
        <x-statistik.zeitraum :zeitraeume="$zeitraeume" :aktiv="$zeitraum" />
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-statistik.kennzahl :titel="__('Anmeldungen')" :wert="number_format($summe['anmeldungen'], 0, ',', '.')" :zusatz="__(':n verschiedene Benutzer', ['n' => $summe['benutzer']])" />
        <x-statistik.kennzahl :titel="__('Gescheiterte Anmeldungen')" :wert="number_format($summe['gescheitert'], 0, ',', '.')" :zusatz="__('falsches Kennwort oder gesperrt')" :warnung="$summe['gescheitert'] > 20" />
        <x-statistik.kennzahl :titel="__('Änderungen')" :wert="number_format($summe['aenderungen'], 0, ',', '.')" :zusatz="__('angelegt, geändert, gelöscht')" />
        <x-statistik.kennzahl :titel="__('Ø Änderungen pro Tag')" :wert="number_format($summe['aenderungen'] / max(1, (int) $zeitraum), 1, ',', '.')" :zusatz="__('im gewählten Zeitraum')" />
    </div>

    <x-panel>
        <div class="mb-3 text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Anmeldungen pro Tag') }}</div>
        <x-statistik.balken :werte="$anmeldungenProTag" farbe="bg-cerulean-500 dark:bg-cerulean-600" hoehe="h-28" />
    </x-panel>

    <x-panel>
        <div class="mb-3 text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Änderungen pro Tag') }}</div>
        <x-statistik.balken :werte="$aenderungenProTag" farbe="bg-emerald-500 dark:bg-emerald-600" hoehe="h-28" />
    </x-panel>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-panel polster="keins" class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Benutzer') }}</th>
                        <th class="px-4 py-2.5 text-right font-semibold">{{ __('Anmeldungen') }}</th>
                        <th class="px-4 py-2.5 text-right font-semibold">{{ __('Änderungen') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Zuletzt') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jeBenutzer as $b)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700">
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $b['name'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $b['anmeldungen'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">{{ $b['aenderungen'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2">{{ Zeit::anzeigen($b['zuletzt'], 'd.m.Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Keine Einträge im Zeitraum.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        <x-panel polster="keins" class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Bereich') }}</th>
                        <th class="px-4 py-2.5 text-right font-semibold">{{ __('Änderungen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jeBereich as $b)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700">
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ __($b['name']) }}</td>
                            <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">{{ $b['anzahl'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Keine Änderungen im Zeitraum.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    </div>
</div>
