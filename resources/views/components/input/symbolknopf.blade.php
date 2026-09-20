{{--
    Der quadratische Knopf, der nur ein Zeichen trägt: Stift, Mülleimer,
    Pfeil nach unten.

    Er stand neunmal von Hand in acht Dateien - in den Gerätelisten, im
    Papierkorb, bei den Dateien, den Diensten und den API-Token. Neunmal
    dieselben zwanzig Klassen, und sie liefen bereits auseinander: mal
    "h-9 w-9", mal "w-9 h-9", mal mit transition-colors und mal ohne, und nur
    an einer einzigen Stelle mit einem sichtbaren Fokusring. Wer mit der
    Tastatur durch eine Tabelle geht, sah an den anderen acht nicht, wo er
    gerade steht.

    href macht ein <a> daraus statt eines <button>: Der Stift in den
    Admin-Listen führt auf eine eigene Seite, der in den Gerätelisten öffnet
    ein Livewire-Modal. Aussehen soll beides gleich.

    titel steht als title UND als aria-label am Knopf. Im Knopf selbst steht
    nur eine Zeichnung, und die hat für ein Vorleseprogramm keinen Namen -
    ohne das Label kündigt es "Schaltfläche" an und sonst nichts.
--}}
@props([
    'titel',
    'ton' => 'blau',
    'href' => null,
])

@php
    $sockel = 'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 '
        .'bg-white shadow-xs transition-colors focus:outline-hidden focus:ring-2 focus:ring-offset-2 '
        .'dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800';

    $toene = [
        'blau' => 'text-cerulean-600 hover:border-cerulean-300 hover:bg-cerulean-50 focus:ring-cerulean-500 dark:text-cerulean-400',
        'rot'  => 'text-red-600 hover:border-red-300 hover:bg-red-50 focus:ring-red-500 dark:text-red-400',
    ];

    $klassen = $sockel.' '.($toene[$ton] ?? $toene['blau']);
@endphp

@if ($href)
    <a href="{{ $href }}" title="{{ $titel }}" aria-label="{{ $titel }}"
        {{ $attributes->merge(['class' => $klassen]) }}>
        {{ $slot }}
    </a>
@else
    <button type="button" title="{{ $titel }}" aria-label="{{ $titel }}"
        {{ $attributes->merge(['class' => $klassen]) }}>
        {{ $slot }}
    </button>
@endif
