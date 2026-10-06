@use('App\Support\SystemWerte')
<div class="p-3 sm:p-5 space-y-4">
    <div>
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('System') }}</div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Was die Installation gerade belegt und wie es sich entwickelt. Wird der Plattenplatz knapp oder der Arbeitsspeicher dauerhaft voll, braucht der Server mehr Ressourcen.') }}
        </p>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-statistik.kennzahl :titel="__('Datenbank')" :wert="SystemWerte::lesbar($db)" :zusatz="__('Tabellen und Indizes')" />
        <x-statistik.kennzahl :titel="__('Dateien')" :wert="SystemWerte::lesbar($dateien)" :zusatz="__('Dokumente und Uploads')" />
        <x-statistik.kennzahl :titel="__('Backups')" :wert="SystemWerte::lesbar($backups)" :zusatz="__('Sicherungen von DokuVault auf diesem Server')" />
        <x-statistik.kennzahl :titel="__('Platte')" :wert="$belegtProzent !== null ? $belegtProzent.' % '.__('belegt') : '—'"
            :zusatz="SystemWerte::lesbar($frei).' '.__('frei von').' '.SystemWerte::lesbar($gesamt)" :warnung="$belegtProzent !== null && $belegtProzent >= 85" />
        <x-statistik.kennzahl :titel="__('Arbeitsspeicher')"
            :wert="$speicher ? (int) round(100 - $speicher['frei'] / $speicher['gesamt'] * 100).' % '.__('belegt') : '—'"
            :zusatz="$speicher ? SystemWerte::lesbar($speicher['frei']).' '.__('frei von').' '.SystemWerte::lesbar($speicher['gesamt']) : __('nur unter Linux lesbar')"
            :warnung="$speicher && $speicher['frei'] / $speicher['gesamt'] < 0.1" />
        <x-statistik.kennzahl :titel="__('Last')"
            :wert="$last ? number_format($last['werte'][0], 2, ',', '.') : '—'"
            :zusatz="$last ? __('1/5/15 Min.: :werte', ['werte' => collect($last['werte'])->map(fn ($w) => number_format($w, 2, ',', '.'))->implode(' / ')]).($last['kerne'] ? ' · '.__(':n Kerne', ['n' => $last['kerne']]) : '') : '—'"
            :warnung="$last && $last['kerne'] && $last['werte'][2] > $last['kerne']" />
        <x-statistik.kennzahl :titel="__('Warteschlange')" :wert="$warteschlange['wartend'] ?? '—'" :zusatz="__('wartende Hintergrundaufträge')" :warnung="($warteschlange['wartend'] ?? 0) > 50" />
        <x-statistik.kennzahl :titel="__('Fehlgeschlagene Aufträge')" :wert="$warteschlange['fehlgeschlagen'] ?? '—'" :zusatz="'PHP '.$php.' · Laravel '.$laravel" :warnung="($warteschlange['fehlgeschlagen'] ?? 0) > 0" />
    </div>

    @foreach ([
        'db_bytes' => [__('Datenbank'), 'bg-cerulean-500 dark:bg-cerulean-600'],
        'dateien_bytes' => [__('Dateien'), 'bg-emerald-500 dark:bg-emerald-600'],
        'backup_bytes' => [__('Backups'), 'bg-amber-500 dark:bg-amber-600'],
        'disk_frei_bytes' => [__('Freier Plattenplatz'), 'bg-gray-400 dark:bg-gray-500'],
    ] as $kennzahl => [$titel, $farbe])
        <x-panel>
            <div class="mb-3 flex items-baseline justify-between">
                <div class="text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ $titel }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('letzte 90 Tage, in MB') }}</div>
            </div>
            @if (count($verlauf[$kennzahl] ?? []) > 1)
                <x-statistik.balken :werte="$verlauf[$kennzahl]" :farbe="$farbe" hoehe="h-28" einheit="MB" />
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('Der Verlauf entsteht ab jetzt – jede Nacht kommt ein Wert dazu.') }}</p>
            @endif
        </x-panel>
    @endforeach

    @if ($tabellen)
        <x-panel>
            <div class="mb-2 text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Größte Tabellen') }}</div>
            <table class="w-full text-sm">
                @foreach ($tabellen as $name => $bytes)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700">
                        <td class="py-1.5 font-mono text-xs text-gray-900 dark:text-gray-100">{{ $name }}</td>
                        <td class="py-1.5 text-right text-gray-600 dark:text-gray-300">{{ SystemWerte::lesbar($bytes) }}</td>
                    </tr>
                @endforeach
            </table>
        </x-panel>
    @endif
</div>
