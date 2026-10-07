@use('App\Support\Zeit')
<div class="p-3 sm:p-5 space-y-4">
    <div>
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Agenten') }}</div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Alle installierten Agenten aller Kunden: wer meldet, wer schweigt, wessen Läufe scheitern und wer noch eine alte Version hat.') }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-statistik.kennzahl :titel="__('Installiert')" :wert="$summe['gesamt']" :zusatz="$jeArt->map(fn ($n, $art) => $n.' '.__(config('custom.dienste.'.$art.'.name', $art)))->implode(' · ')" />
        <x-statistik.kennzahl :titel="__('Melden')" :wert="$summe['aktiv']" :zusatz="__('in den letzten :stunden Stunden erreichbar', ['stunden' => \App\Models\Setting::agentStillStunden()])" />
        <x-statistik.kennzahl :titel="__('Melden nicht')" :wert="$summe['still']" :zusatz="__('seit über :stunden Stunden still', ['stunden' => \App\Models\Setting::agentStillStunden()])" :warnung="$summe['still'] > 0" />
        <x-statistik.kennzahl :titel="__('Mit Fehlern')" :wert="$summe['fehler']" :zusatz="__('letzter Lauf fehlgeschlagen')" :warnung="$summe['fehler'] > 0" />
        <x-statistik.kennzahl :titel="__('Alte Version')" :wert="$summe['veraltet']" :zusatz="__('Selbst-Update noch nicht angekommen')" :warnung="$summe['veraltet'] > 0" />
    </div>

    <x-panel polster="keins" class="overflow-x-auto">
        <table class="w-full min-w-176 text-left text-sm text-gray-500 dark:text-gray-400">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Kunde') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Rechner') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Art') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Zustand') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Letzter Lauf') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Version') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agenten as $a)
                    <tr wire:key="agent-{{ $a->id }}" class="border-b border-gray-100 last:border-0 dark:border-gray-700">
                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                            @if ($a->customer)
                                <a href="{{ route('agent.index', $a->customer) }}" class="hover:text-cerulean-600">{{ $a->customer->name }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $a->hostname }}</td>
                        <td class="px-4 py-2">{{ __(config('custom.dienste.'.$a->kind.'.name', $a->kind)) }}</td>
                        <td class="px-4 py-2">
                            @if ($a->isStale())
                                <span class="font-medium text-red-600 dark:text-red-400">{{ __('meldet nicht') }}</span>
                            @elseif ($a->fehler)
                                <span class="font-medium text-red-600 dark:text-red-400">{{ __('Fehler: :rollen', ['rollen' => implode(', ', $a->fehler)]) }}</span>
                            @else
                                <span class="text-green-700 dark:text-green-400">{{ __('in Ordnung') }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-2">{{ Zeit::anzeigen($a->last_run_at, 'd.m.Y H:i', __('noch nie')) }}</td>
                        <td @class(['px-4 py-2 font-mono text-xs', 'text-amber-600 dark:text-amber-400' => $a->veraltet])>
                            {{ $a->version ?? '—' }}@if ($a->veraltet) <span class="font-sans">({{ __('aktuell: :v', ['v' => $aktuell[$a->kind]]) }})</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Noch kein Agent installiert.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-panel>
</div>
