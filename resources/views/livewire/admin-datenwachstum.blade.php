<div class="p-3 sm:p-5 space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Datenwachstum') }}</div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Wie viel die Doku enthält und wie es wächst – insgesamt und je Kunde. Ein Wert pro Tag, aufgenommen jede Nacht.') }}</p>
        </div>
        <x-statistik.zeitraum :zeitraeume="$zeitraeume" :aktiv="$zeitraum" />
    </div>

    @if (! $erster)
        <x-panel><p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Noch keine Werte – der erste kommt in der nächsten Nacht.') }}</p></x-panel>
    @else
        {{-- Per type: now and the change in the period; a click shows its course. --}}
        <x-panel polster="keins" class="overflow-x-auto">
            <table class="w-full min-w-120 text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Bereich') }}</th>
                        <th class="px-4 py-2.5 text-right font-semibold">{{ __('Jetzt') }}</th>
                        <th class="px-4 py-2.5 text-right font-semibold">{{ __('seit :datum', ['datum' => $erster->format('d.m.Y')]) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($uebersicht as $z)
                        @php($diff = $z['jetzt'] - $z['anfang'])
                        <tr wire:key="art-{{ $z['slug'] }}" wire:click="$set('kennzahl', '{{ $z['slug'] }}')" @class([
                            'cursor-pointer border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50',
                            'bg-cerulean-50 dark:bg-cerulean-900/20' => $z['slug'] === $kennzahl,
                        ])>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ __($z['name']) }}</td>
                            <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">{{ number_format($z['jetzt'], 0, ',', '.') }}</td>
                            <td @class(['px-4 py-2 text-right', 'text-green-600 dark:text-green-400' => $diff > 0, 'text-red-600 dark:text-red-400' => $diff < 0])>{{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>

        <x-panel>
            <div class="mb-3 text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __($arten[$kennzahl] ?? $kennzahl) }} · {{ __('Verlauf') }}</div>
            @if (count($verlauf) > 1)
                <x-statistik.balken :werte="$verlauf" farbe="bg-emerald-500 dark:bg-emerald-600" />
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('Der Verlauf entsteht ab jetzt – jede Nacht kommt ein Wert dazu.') }}</p>
            @endif
        </x-panel>

        <x-panel polster="keins" class="overflow-x-auto">
            <table class="w-full min-w-120 text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Kunde') }}</th>
                        <th class="px-4 py-2.5 text-right font-semibold">{{ __($arten[$kennzahl] ?? $kennzahl) }}</th>
                        <th class="px-4 py-2.5 text-right font-semibold">{{ __('Veränderung') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jeKunde as $z)
                        @php($diff = $z['jetzt'] - $z['anfang'])
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700">
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $z['kunde'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">{{ number_format($z['jetzt'], 0, ',', '.') }}</td>
                            <td @class(['px-4 py-2 text-right', 'text-green-600 dark:text-green-400' => $diff > 0, 'text-red-600 dark:text-red-400' => $diff < 0])>{{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Kein Kunde hat davon etwas.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    @endif
</div>
