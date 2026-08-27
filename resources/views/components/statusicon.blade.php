@props(['value'])

{{-- Haken, Kreuz oder Strich statt "Aktiv"/"Deaktiviert": In einer Liste
     erfasst man ein Zeichen schneller als ein Wort, und die drei Zustaende
     sind auf einen Blick unterscheidbar.

     title, damit die Bedeutung nicht allein an der Form haengt - fuer
     Vorlesewerkzeuge und fuer alle, denen Gruen und Grau gleich aussehen. --}}

@if ($value === null)
    <span class="text-gray-400 dark:text-gray-500" title="{{ __('unbekannt') }}">—</span>
@elseif ($value)
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"
        class="h-5 w-5 text-green-600 dark:text-green-400" role="img" aria-label="{{ __('Aktiv') }}">
        <title>{{ __('Aktiv') }}</title>
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
    </svg>
@else
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"
        class="h-5 w-5 text-gray-400 dark:text-gray-500" role="img" aria-label="{{ __('Deaktiviert') }}">
        <title>{{ __('Deaktiviert') }}</title>
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
@endif
