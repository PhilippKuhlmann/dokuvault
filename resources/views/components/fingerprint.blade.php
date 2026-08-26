@props(['value'])

{{-- Gekuerzt statt vollstaendig: Ein Fingerprint ist 50 Zeichen ohne
     Trennstellen und bricht in einer Tabellenspalte auf fuenf Zeilen um - er
     bestimmt dann die Zeilenhoehe, obwohl man ihn nur beim Vergleichen liest.
     Das "SHA256:" davor ist bei jedem gleich und traegt nichts bei.

     Der ganze Wert steht im Titel und geht ueber den Knopf in die
     Zwischenablage; die Suche arbeitet ohnehin auf dem vollstaendigen Wert. --}}

@if (blank($value))
    <span class="text-gray-400 dark:text-gray-500">—</span>
@else
    @php ($kern = str_starts_with($value, 'SHA256:') ? substr($value, 7) : $value)

    <div class="flex items-center gap-1.5" x-data="{ copied: false }">
        <span class="font-mono text-xs text-gray-600 dark:text-gray-300" title="{{ $value }}">
            {{ Str::limit($kern, 10, '…') }}
        </span>
        <input type="hidden" x-ref="fp" value="{{ $value }}">
        <button type="button" tabindex="-1" title="{{ __('Fingerprint kopieren') }}"
            x-on:click="copyText($refs.fp.value); copied = true; setTimeout(() => copied = false, 1500)"
            class="text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300">
            <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
            </svg>
            <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 text-green-600 dark:text-green-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </button>
    </div>
@endif
