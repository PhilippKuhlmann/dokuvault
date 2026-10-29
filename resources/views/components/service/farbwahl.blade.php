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
            class="w-32 rounded-lg border-gray-300 font-mono text-sm shadow-xs focus:border-cerulean-500 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">

        <span class="px-3 py-1 text-sm rounded"
            x-bind:style="$kachelStil(hex)">{{ $name ?: __('Vorschau') }}</span>
    </div>

    <x-input.fehler feld="color" />
</div>
