@props(['zustand'])

{{--
    Die zweite Stufe in einer Liste: ein Zeichen statt eines Wortes. In einer
    Tabelle mit acht Spalten erfasst man "Haken" schneller als "eingerichtet",
    und die Spalte hoert auf, so breit zu sein wie ihr laengster Text.

    Drei Zustaende, und der mittlere ist ausdruecklich keiner der beiden
    anderen: Bei "verlangt, offen" ist der Zugang nicht geschuetzt, aber jemand
    hat entschieden, dass er es sein soll - das ist etwas anderes als "niemand
    verlangt es". Bernstein wie beim EOL-Abzeichen; dort heisst die Farbe
    ebenfalls "steht noch an".

    Das Wort steht im title und im aria-label, nicht in der Zeile: Die
    Bedeutung darf nicht allein an Form und Farbe haengen - fuer
    Vorlesewerkzeuge und fuer alle, denen Gruen und Bernstein gleich aussehen.
--}}

@if ($zustand === 'eingerichtet')
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"
        class="h-5 w-5 text-green-600 dark:text-green-400" role="img" aria-label="{{ __('eingerichtet') }}">
        <title>{{ __('eingerichtet') }}</title>
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
    </svg>
@elseif ($zustand === 'offen')
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
        class="h-5 w-5 text-amber-600 dark:text-amber-400" role="img" aria-label="{{ __('verlangt, offen') }}">
        <title>{{ __('verlangt, offen') }}</title>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@else
    <span class="text-gray-400 dark:text-gray-500" title="{{ __('nicht eingerichtet') }}">—</span>
@endif
