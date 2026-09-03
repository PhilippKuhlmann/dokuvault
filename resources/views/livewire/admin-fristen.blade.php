<div class="p-3 sm:p-5 space-y-6">
    <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Fristen') }}</div>

    <div class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Vorwarnzeit') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Wie viele Tage vorher etwas als „läuft bald ab“ gilt. Bereits Abgelaufenes wird immer angezeigt, unabhängig von dieser Zahl.') }}
        </p>

        <div class="space-y-6">
            @foreach ([
                ['vertraege', __('Lizenzen, Zertifikate und Domains'),
                    __('Auf dem Kundendashboard und in der Übersicht über alle Kunden.')],
                ['garantie', __('Garantien'),
                    __('Auf dem Kundendashboard, über alle Gerätearten hinweg.')],
                ['eol', __('Support-Ende der Betriebssysteme'),
                    __('Für das Abzeichen am Betriebssystem und für die Liste unter Betriebssysteme.')],
            ] as [$feld, $label, $wirkung])
                <div wire:key="frist-{{ $feld }}">
                    <x-input.label for="{{ $feld }}" :value="$label" />

                    <div class="mt-1 flex items-center gap-2">
                        <x-input.field id="{{ $feld }}" type="number" min="1" max="1825"
                            wire:model.live.debounce.600ms="{{ $feld }}" class="w-32" />
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Tage') }}</span>
                        <span wire:loading wire:target="{{ $feld }}" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
                    </div>

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $wirkung }}</p>

                    <x-input.fehler :feld="$feld" />
                </div>
            @endforeach
        </div>
    </div>

    {{-- Eigene Karte, weil es keine Warnung ist, sondern eine Löschfrist. Wer
         hier eine Zahl ändert, ändert, wie lange Zugangsdaten im Klartext auf
         der Platte liegen. --}}
    <div class="max-w-3xl p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('PDF-Ausgaben aufbewahren') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Eine fertige PDF-Ausgabe enthält alle Zugangsdaten des Kunden im Klartext. Danach wird sie gelöscht — wer sie noch braucht, gibt den Auftrag neu.') }}
        </p>

        <x-input.label for="pdfStunden" :value="__('Aufbewahrung')" />

        <div class="mt-1 flex items-center gap-2">
            <x-input.field id="pdfStunden" type="number" min="1" max="8760"
                wire:model.live.debounce.600ms="pdfStunden" class="w-32" />
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Stunden') }}</span>
            <span wire:loading wire:target="pdfStunden" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
        </div>

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Aufgeräumt wird stündlich. Eine Datei liegt also bis zu eine Stunde länger, als hier steht.') }}
        </p>

        <x-input.fehler feld="pdfStunden" />
    </div>
</div>
