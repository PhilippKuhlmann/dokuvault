{{-- feld: siehe x-input.text - ein fehlerhaftes Feld umrandet sich rot. --}}
@props(['name', 'feld' => null])

@php ($fehler = ($feld ?? $name) && $errors->has($feld ?? $name))

<select
    name="{{ $name }}"
    @if ($fehler) aria-invalid="true" @endif
    {{ $attributes->merge([
        'class' => 'rounded-lg shadow-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-300 '.($fehler
            ? 'border-red-500 dark:border-red-500 focus:border-red-500 dark:focus:border-red-500 focus:ring-red-500 dark:focus:ring-red-500'
            : 'border-gray-300 dark:border-gray-700 focus:border-cerulean-500 dark:focus:border-cerulean-500 focus:ring-cerulean-500 dark:focus:ring-cerulean-500'),
    ]) }}
>
    {{ $slot }}
</select>
