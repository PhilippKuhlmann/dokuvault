<div class="flex items-center gap-2" x-data="{ copied: false }"
    x-on:kennwort-bereit="copyText($event.detail.wert); copied = true; setTimeout(() => copied = false, 1500)">
    {{-- Immer dasselbe Feld und dieselben zwei Knöpfe - ob verdeckt oder offen.
         So springt die Spalte beim Aufdecken nicht: Vorher kam offen ein
         zweiter Knopf (Kopieren) dazu, und die Zelle wuchs.

         Der Wert steht erst im DOM, wenn er angezeigt wurde. Kopieren geht auch
         zugeklappt: Dann holt der Server den Wert einmalig in die Zwischenablage
         (protokolliert und gebremst wie das Anzeigen), ohne ihn aufzudecken. --}}
    <input x-ref="pw" type="text" disabled value="{{ $offen ? $wert : '••••••••••' }}"
        class="{{ $width }} min-w-0 p-0 text-sm font-mono bg-transparent border-0 {{ $offen ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500 select-none' }}">

    {{-- Auge: aufdecken bzw. verbergen. --}}
    <button type="button" tabindex="-1" wire:click="{{ $offen ? 'verbergen' : 'zeigen' }}"
        title="{{ $offen ? __('Passwort verbergen') : __('Passwort anzeigen') }}"
        class="shrink-0 text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300">
        @if ($offen)
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        @endif
    </button>

    {{-- Kopieren: offen aus dem DOM, zugeklappt über den Server - ohne
         aufzudecken. --}}
    <button type="button" tabindex="-1" title="{{ __('Passwort kopieren') }}"
        @if ($offen)
            x-on:click="copyText($refs.pw.value); copied = true; setTimeout(() => copied = false, 1500)"
        @else
            wire:click="kopieren"
        @endif
        class="shrink-0 text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300">
        <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
        </svg>
        <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5 text-green-600 dark:text-green-400">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
    </button>

    {{-- Greift die Bremse, steht hier der Hinweis - statt eines still
         ausbleibenden Werts. --}}
    @error('kennwort') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
</div>
