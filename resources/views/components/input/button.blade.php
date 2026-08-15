@props([
    'label',
    'type' => 'submit',
    'color' => 'blue',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 rounded-lg font-DINPro-bold shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50';

    // "feld" trifft die Hoehe von x-input.text und x-input.select, damit ein Knopf
    // neben Eingabefeldern nicht 6 px kleiner dasteht. Das Feld rechnet
    // 1px Rand + 8 + 24 (leading-6 bei 16px Schrift) + 8 + 1 = 42; der Knopf kommt
    // mit leading-6 und einem durchsichtigen Rand auf dieselbe Summe, behaelt
    // aber die kleinere Schrift.
    $sizes = [
        'md' => 'px-4 py-2 text-sm',
        'sm' => 'px-3 py-1.5 text-xs',
        'feld' => 'px-4 py-2 text-sm leading-6 border border-transparent',
    ];

    $variants = [
        'blue' => 'bg-cerulean-600 text-white hover:bg-cerulean-700 focus:ring-cerulean-500',
        'red'  => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'gray' => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-cerulean-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600',
    ];

    $classes = $base . ' ' . ($sizes[$size] ?? $sizes['md']) . ' ' . ($variants[$color] ?? $variants['blue']);
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $label }}
</button>
