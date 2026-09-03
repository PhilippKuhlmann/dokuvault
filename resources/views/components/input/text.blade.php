{{--
    feld: Der Name des Feldes in der Validierung. Ist er angegeben und liegt
    dafür ein Fehler vor, umrandet sich das Feld rot und meldet sich als
    fehlerhaft an Vorleseprogramme.

    Ohne das steht der Fehler nur als kleiner Text darunter, und man muss ihn
    lesen, um zu sehen, welches Feld gemeint ist - bei einem Formular mit zwölf
    Feldern ist das Suchen.
--}}
@props(['disabled' => false, 'feld' => null])

@php ($fehler = $feld && $errors->has($feld))

<input {{ $disabled ? 'disabled' : '' }}
    @if ($fehler) aria-invalid="true" @endif
    {{ $attributes->merge(['class' => 'rounded-lg shadow-sm dark:bg-gray-700 dark:text-gray-100 '.($fehler
        ? 'border-red-500 dark:border-red-500 focus:border-red-500 focus:ring-red-500'
        : 'border-gray-300 dark:border-gray-700 focus:border-cerulean-500 focus:ring-cerulean-500')]) }}>
