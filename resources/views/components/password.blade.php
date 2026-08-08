@props(['value', 'width' => 'w-full'])

{{-- Maskiertes Passwortfeld mit Auge und Kopierknopf.
     Ausgelagert, weil es an mehreren Stellen gebraucht wird - in der Geräteliste
     und im Zugangsdaten-Block am Gerät. --}}
<div class="flex items-center gap-2" x-data="{ show: false, copied: false }">
    <input x-ref="pw" :type="show ? 'text' : 'password'" disabled value="{{ $value }}"
        class="{{ $width }} p-0 text-sm font-mono bg-transparent border-0 text-gray-900 dark:text-gray-100">

    <button type="button" tabindex="-1" x-on:click="show = !show" title="{{ __('Passwort anzeigen') }}"
        class="shrink-0 text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    </button>

    <button type="button" tabindex="-1" title="{{ __('Passwort kopieren') }}"
        x-on:click="copyText($refs.pw.value); copied = true; setTimeout(() => copied = false, 1500)"
        class="shrink-0 text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300">
        <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
        </svg>
        <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5 text-green-600 dark:text-green-400">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
    </button>
</div>
