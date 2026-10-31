@props([
    // Eine Zeile aus Kundenuebersicht: name, art, datum, tage, typ, id, url.
    'frist',
    // Die Art unter dem Namen - bei Garantien und Support-Ende nötig, weil
    // dort Geräte aller Arten in einer Kachel stehen. In einer Kachel, die
    // schon "Ablaufende Lizenzen" heißt, wäre sie doppelt.
    'untertitel' => false,
    // Ab wann gelb statt grau. Ein Gerät will länger vorher ersetzt werden,
    // als eine Lizenz verlängert.
    'schwelle' => 14,
    // Weit entferntes als Monat statt "in 400 Tagen".
    'fernDatum' => false,
])

{{-- Eine Zeile einer Fristen-Kachel: Name, darunter die Art, rechts die
     Tage. Das Rot/Gelb/Grau steht nur hier - vorher stand dieselbe
     if-Kette viermal in der Vorlage, jede mit eigenen Grenzen. --}}
<a href="{{ $frist['url'] }}"
    class="flex items-center justify-between gap-3 py-2.5 -mx-2 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
    <span class="min-w-0">
        <span class="block truncate text-gray-800 dark:text-gray-100">{{ $frist['name'] }}</span>
        @if ($untertitel)
            <span class="block truncate text-xs text-gray-400 dark:text-gray-500">{{ __($frist['art']) }}</span>
        @endif
    </span>
    @if ($frist['tage'] < 0)
        <span class="shrink-0 text-sm font-medium text-red-600 dark:text-red-400">{{ __('abgelaufen') }}</span>
    @elseif ($frist['tage'] == 0)
        <span class="shrink-0 text-sm font-medium text-red-600 dark:text-red-400">{{ __('heute') }}</span>
    @elseif ($frist['tage'] <= $schwelle)
        <span class="shrink-0 text-sm font-medium text-amber-600 dark:text-amber-400">{{ __('in :tage Tagen', ['tage' => $frist['tage']]) }}</span>
    @elseif ($fernDatum)
        <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ $frist['datum']->format('m/Y') }}</span>
    @else
        <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ __('in :tage Tagen', ['tage' => $frist['tage']]) }}</span>
    @endif
</a>
