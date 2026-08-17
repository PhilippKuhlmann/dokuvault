{{-- wire:poll nur solange etwas laeuft: Ein Dauer-Poll auf jeder
     Dashboard-Seite waere eine Anfrage alle drei Sekunden, fuer nichts. --}}
<div class="flex items-center gap-3" @if ($nachfragen) wire:poll.3s @endif>

    @if ($auftrag?->istFertig())
        <a href="{{ route('customer.pdf-download', [$kunde, $auftrag]) }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold shadow-sm hover:bg-cerulean-700 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors">
            <x-svg.document class="w-4 h-4" />
            {{ __('PDF laden') }}
            @if ($auftrag->groesseLesbar())
                <span class="font-normal opacity-80">({{ $auftrag->groesseLesbar() }})</span>
            @endif
        </a>

        {{-- Neu erstellen bleibt moeglich: Die Doku aendert sich, das PDF nicht. --}}
        <button type="button" wire:click="starten"
            class="text-xs text-gray-500 hover:text-cerulean-700 dark:text-gray-400 dark:hover:text-cerulean-400">
            {{ __('neu erstellen') }}
        </button>

    @elseif ($auftrag?->haengt())
        {{-- Ohne Cron-Zeile bleibt der Auftrag liegen. Das ist die einzige
             Stelle, an der das jemandem auffaellt - also hier auch sagen,
             woran es liegt. --}}
        <div class="text-sm text-amber-700 dark:text-amber-400">
            {{ __('Der Auftrag wartet seit über fünf Minuten.') }}
            <span class="block text-xs opacity-80">{{ __('Läuft auf dem Server die geplante Aufgabe (schedule:run)?') }}</span>
        </div>

    @elseif ($auftrag?->laeuftNoch())
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <svg class="w-4 h-4 animate-spin text-cerulean-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
            {{ __('PDF wird erstellt …') }}
        </div>

    @else
        @if ($auftrag?->status === \App\Models\PdfExport::FEHLER)
            <div class="text-sm text-red-600 dark:text-red-400" title="{{ $auftrag->error }}">
                {{ __('Erstellung fehlgeschlagen.') }}
            </div>
        @endif

        <button type="button" wire:click="starten"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold shadow-sm hover:bg-cerulean-700 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors">
            {{ __('PDF erstellen') }}
        </button>
    @endif

</div>
