{{-- Sieht aus und bedient sich wie jede andere Aenderung in dieser Spalte:
     zugeklappt nur "anzeigen", aufgeklappt "feld: wert". Der Unterschied ist
     unsichtbar - der Wert kommt erst auf Klick vom Server, nicht aus dem
     bereits geladenen Quelltext. --}}
<div>
    <button type="button" wire:click="{{ $offen ? 'verbergen' : 'zeigen' }}"
        class="text-cerulean-600 hover:text-cerulean-700 text-sm">
        {{ $offen ? __('verbergen') : __('anzeigen') }}
    </button>

    @if ($offen)
        <div class="mt-2 space-y-1">
            @forelse ($werte as $eintrag)
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-gray-500">{{ __($eintrag['feld']) }}:</span>
                    <x-password :value="$eintrag['wert']" width="w-32" />
                    {{-- Der Unterschied muss dranstehen: Sonst haelt man das
                         geltende Kennwort fuer das abgeloeste. --}}
                    @if ($eintrag['aktuell'] ?? false)
                        <span class="text-gray-400 dark:text-gray-500">{{ __('gilt jetzt – vorher war keines gesetzt') }}</span>
                    @else
                        <span class="text-gray-400 dark:text-gray-500">{{ __('galt vorher') }}</span>
                    @endif
                </div>
            @empty
                {{-- Die Frist ist abgelaufen oder jemand hat den Eintrag
                     geloescht. Dass die Aenderung stattfand, bleibt trotzdem. --}}
                <div class="text-xs">
                    <span class="text-gray-500">{{ collect($felder)->map(fn ($f) => __(config('custom.secret_field_labels')[$f] ?? $f))->join(', ') }}:</span>
                    <span class="text-gray-400 dark:text-gray-500">{{ __('Nicht mehr aufbewahrt') }}</span>
                </div>
            @endforelse
        </div>
    @endif
</div>
