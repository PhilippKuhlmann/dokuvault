@props(['label', 'zaehler' => null])

{{-- Ein Eintrag in der Kopfzeile einer Gerätekarte: kleine Beschriftung, davor
     der Wert. Der Zähler zeigt an, dass es mehr davon gibt (vier IP-Adressen,
     zwei Zugänge) - sonst sieht man das erst weiter unten in der Karte. --}}
<div class="flex items-center gap-2 text-sm">
    <span class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $label }}</span>
    <span class="text-gray-900 dark:text-gray-100">{{ $slot }}</span>

    @if ($zaehler > 0)
        <span title="{{ __('Weitere Einträge in der Karte') }}"
            class="inline-flex h-[18px] min-w-[20px] items-center justify-center rounded bg-cerulean-100 px-1.5 font-mono text-[11px] font-semibold text-cerulean-800 dark:bg-cerulean-900 dark:text-cerulean-200">
            +{{ $zaehler }}
        </span>
    @endif
</div>
