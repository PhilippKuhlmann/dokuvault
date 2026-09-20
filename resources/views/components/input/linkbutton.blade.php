@props([
    'label',
    'link',
    'color' => 'blue'
])

{{--
    "knopf" traegt dieselben Klassen wie x-input.button in blau/md. Ein
    <a> statt eines <button>, weil das Ziel eine Adresse ist (rustdesk://...)
    und kein Formular - aussehen soll es aber wie jeder andere Knopf der
    Anwendung. "blau" bleibt der schlichte Textlink; beides wird gebraucht.
--}}
@php
    $klassen = match ($color) {
        'knopf' => 'inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm '
            .'font-DINPro-bold shadow-xs transition-colors bg-cerulean-600 text-white '
            .'hover:bg-cerulean-700 focus:outline-hidden focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 '
            .'dark:focus:ring-offset-gray-800',
        'gray' => 'py-2 px-4 text-sm bg-gray-900 hover:bg-gray-400 text-gray-100 dark:bg-gray-700 rounded-lg',
        default => 'text-cerulean-500 hover:text-cerulean-600 font-DINPro-bold',
    };
@endphp

<a href="{{ $link }}" {{ $attributes->merge(['class' => $klassen]) }}>
    {{ $label }}
</a>
