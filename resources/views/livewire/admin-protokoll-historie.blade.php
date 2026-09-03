<div class="p-3 sm:p-5 space-y-4">

    <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Protokoll-Historie') }}</div>

    <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
        {{ __('Wie lange Einträge im Protokoll stehen bleiben. Die bisherigen Kennwörter hängen daran und gehen mit — danach zeigt das Protokoll die Änderung weiter an, den alten Wert aber nicht mehr.') }}
    </p>

    <div class="max-w-3xl rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <x-input.label :value="__('Aufbewahren (Tage)')" />
                <x-input.field type="number" min="0" max="3650" wire:model.live.debounce.500ms="tage" class="mt-1 w-24" />
            </div>
            <x-input.button type="button" size="feld" wire:click="speichern" :label="__('Speichern')" />
        </div>

        <x-input.fehler feld="tage" />

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {{ __('0 heißt: unbegrenzt aufbewahren.') }}
        </p>

        {{-- Eine Zahl ohne Folgenabschätzung ist eine Zumutung: "365" sagt einem
             nicht, ob damit drei Einträge verschwinden oder dreitausend. --}}
        <div class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-700">
            <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Einträge') }}</dt>
                    <dd class="mt-0.5 text-lg font-DINPro-bold text-gray-900 dark:text-gray-100">{{ $gesamt }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Ältester') }}</dt>
                    <dd class="mt-0.5 text-lg font-DINPro-bold text-gray-900 dark:text-gray-100">
                        {{ $aeltester ? \Carbon\Carbon::parse($aeltester)->format('d.m.Y') : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Davon Kennwörter') }}</dt>
                    <dd class="mt-0.5 text-lg font-DINPro-bold text-gray-900 dark:text-gray-100">{{ $kennwoerter }}</dd>
                </div>
            </dl>

            @if ($tage > 0)
                <p @class([
                    'mt-4 rounded-lg p-3 text-sm',
                    'bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300' => $betroffen > 0,
                    'bg-gray-50 text-gray-600 dark:bg-gray-900/40 dark:text-gray-400' => $betroffen === 0,
                ])>
                    @if ($betroffen > 0)
                        {{ __('Der nächtliche Lauf entfernt :anzahl Einträge, die älter sind.', ['anzahl' => $betroffen]) }}
                        @if ($betroffeneKennwoerter > 0)
                            {{ __('Darunter :anzahl bisherige Kennwörter.', ['anzahl' => $betroffeneKennwoerter]) }}
                        @endif
                    @else
                        {{ __('Zurzeit ist nichts älter als diese Frist.') }}
                    @endif
                </p>
            @endif
        </div>
    </div>
</div>
