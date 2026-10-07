@use('App\Models\Setting')
<div class="p-3 sm:p-5 space-y-6">
    <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Fristen') }}</div>

    <x-panel class="max-w-3xl">
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
    </x-panel>

    {{-- Eigene Karte, weil es keine Warnung ist, sondern eine Löschfrist. Wer
         hier eine Zahl ändert, ändert, wie lange Zugangsdaten im Klartext auf
         der Platte liegen. --}}
    <x-panel class="max-w-3xl">
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
    </x-panel>

    {{-- Formerly fixed in code: 90 days and two years. --}}
    <x-panel class="max-w-3xl">
        <div class="text-xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Statistik aufbewahren') }}</div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Wie weit die Auswertungen unter Statistik zurückreichen. Ältere Werte werden nachts gelöscht.') }}
        </p>

        <div class="space-y-6">
            <x-einstellung.zahl feld="statistikApiTage" :label="__('API-Auslastung')" :einheit="__('Tage')"
                :min="Setting::ZAHLEN[Setting::STATISTIK_API_TAGE][1]" :max="Setting::ZAHLEN[Setting::STATISTIK_API_TAGE][2]"
                :hinweis="__('Anfragen von Agenten und API-Token je Stunde und Endpunkt. Eine Zeile je Stunde und Endpunkt – auch ein Jahr bleibt klein.')" />
            <x-einstellung.zahl feld="statistikMonate" :label="__('Verläufe')" :einheit="__('Monate')"
                :min="Setting::ZAHLEN[Setting::STATISTIK_MONATE][1]" :max="Setting::ZAHLEN[Setting::STATISTIK_MONATE][2]"
                :hinweis="__('Der nächtliche Schnappschuss für System und Datenwachstum – eine Zeile je Tag und Kennzahl.')" />
        </div>
    </x-panel>
</div>
