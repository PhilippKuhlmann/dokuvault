@props(['label', 'zaehler' => null, 'breite' => null, 'titel' => null])

{{-- Ein Eintrag in der Kopfzeile einer Gerätekarte: kleine Beschriftung, davor
     der Wert. Der Zähler zeigt an, dass es mehr davon gibt (vier IP-Adressen,
     zwei Zugänge) - sonst sieht man das erst weiter unten in der Karte.

     breite: a fixed width (Tailwind class) - in a list of folded cards the
     same value then stands in the same column on every line, the eye no
     longer jumps (design 1A). Then the label stands above the value, two
     lines like a column head: the label no longer eats the width of the
     value. The value is cut off and readable in titel. An empty value shows
     a dash, so the column stays where it is. --}}
<div @class([
    'flex min-w-0',
    'text-sm' => ! $breite,
    'text-[13px]' => $breite,
    'flex-col gap-0.5' => $breite,
    'items-center gap-2' => ! $breite,
    $breite => $breite,
])>
    {{-- gray-500 / dark gray-400: the lighter shades stayed under 4.5:1 for
         text this small (2.5:1 light, 3:1 dark). --}}
    <span class="shrink-0 text-[11px] font-semibold uppercase leading-none tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>

    <div class="flex min-w-0 items-center gap-2">
        {{-- Livewire leaves its BLOCK comment markers in a slot built from @if:
             only what is more than comments counts as a value. --}}
        @if (trim(preg_replace('/<!--.*?-->/s', '', (string) $slot)) !== '')
            <span @class(['text-gray-900 dark:text-gray-100', 'min-w-0 truncate' => $breite]) @if ($titel) title="{{ $titel }}" @endif>{{ $slot }}</span>
        @else
            <span class="text-gray-400 dark:text-gray-500">—</span>
        @endif

        @if ($zaehler > 0)
            <span title="{{ __('Weitere Einträge in der Karte') }}"
                class="inline-flex h-[18px] min-w-[20px] shrink-0 items-center justify-center rounded bg-cerulean-100 px-1.5 font-mono text-[11px] font-semibold text-cerulean-800 dark:bg-cerulean-900 dark:text-cerulean-200">
                +{{ $zaehler }}
            </span>
        @endif
    </div>
</div>
