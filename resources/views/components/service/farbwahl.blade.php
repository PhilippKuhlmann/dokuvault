@props(['farbe' => '#3391f0', 'name' => null])

{{-- Farbwähler und Hex-Feld auf demselben Wert: Der Wähler ist zum Suchen da,
     das Textfeld zum Einfügen einer Farbe aus der Kundendoku. Die Vorschau
     daneben zeigt sofort, wie die Kachel später aussieht - inklusive der
     Schriftfarbe, die sich aus der Helligkeit ergibt. --}}
{{-- @js statt '{{ }}': Der Wert kommt als old() zurueck, wenn die
     Farbpruefung fehlschlaegt - also roh, wie eingetippt. Im Attribut
     schuetzt das HTML-Escaping nicht, siehe admin/setting/index. --}}
<div class="flex flex-col mt-2" x-data="{ hex: @js($farbe) }">
    <x-input.label for="color" :value="__('Farbe')" />

    <div class="mt-1 flex flex-wrap items-center gap-3">
        <input type="color" x-model="hex" aria-label="{{ __('Farbe wählen') }}"
            class="h-10 w-14 cursor-pointer rounded-lg border border-gray-300 bg-white p-1 dark:border-gray-600 dark:bg-gray-700">

        <input type="text" name="color" x-model="hex" maxlength="7" placeholder="#3391f0" spellcheck="false"
            class="w-32 rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-cerulean-500 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">

        <span class="px-3 py-1 text-sm rounded"
            x-bind:style="(() => {
                if (! hex.match(/^#[0-9a-fA-F]{6}$/)) { return 'background-color:#e5e7eb;color:#111827'; }
                const [r, gr, b] = [1, 3, 5].map(i => parseInt(hex.substr(i, 2), 16) / 255);
                const hell = 0.299 * r + 0.587 * gr + 0.114 * b;
                return 'background-color:' + hex + ';color:' + (hell > 0.6 ? '#111827' : '#ffffff');
            })()">{{ $name ?: __('Vorschau') }}</span>
    </div>

    @error('color')
        <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
    @enderror
</div>
