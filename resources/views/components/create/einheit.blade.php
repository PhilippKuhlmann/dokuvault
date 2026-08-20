{{--
    Zahlenfeld mit fester Einheit dahinter - man tippt "250", im Feld steht
    "250 | Mbit/s". Die Einheit ist Beschriftung, kein Eingabewert: Sie wandert
    nicht mit in die Datenbank, und niemand muss sie abtippen.
--}}
@props([
    'label',
    'name',
    'einheit',
    'default' => '',
    // Im Modal gibt es kein Formular zum Abschicken - dann bindet das Feld
    // direkt an die Livewire-Komponente.
    'wireModel' => null,
])

<div @class(['flex flex-col mt-2', 'w-full sm:w-1/2' => ! $wireModel])>
    <x-input.label for="{{ $name }}" value="{{ $label }}" />

    <div class="mt-1 flex">
        {{-- rounded-r-none + -mr-px: Feld und Einheit sollen wie ein Element
             wirken, ohne doppelte Trennlinie in der Mitte. --}}
        @if ($wireModel)
            <x-input.field id="{{ $name }}" type="number" min="0" step="1"
                wire:model="{{ $wireModel }}" class="-mr-px w-full rounded-r-none" />
        @else
            <x-input.field id="{{ $name }}" name="{{ $name }}" type="number" min="0" step="1"
                class="-mr-px w-full rounded-r-none" value="{{ old($name) ?? $default }}" />
        @endif

        <span class="inline-flex shrink-0 items-center rounded-r-sm border border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-300">
            {{ $einheit }}
        </span>
    </div>
</div>
