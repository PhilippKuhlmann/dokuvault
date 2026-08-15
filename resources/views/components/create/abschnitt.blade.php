{{--
    Ein Sachgebiet im Formular: Ueberschrift, optionaler Hinweis, darunter ein
    zweispaltiges Raster. Die Feldkomponenten (x-create.singlerow,
    x-create.options, x-edit.select) bringen jeweils ein eigenes <div> mit und
    werden dadurch zu Rasterzellen; ihr mt-2 gibt den Zeilenabstand.

    Ein Feld ueber die volle Breite bekommt class="sm:col-span-2".
--}}
@props([
    'titel',
    'hinweis' => null,
    'erste' => false,
])

<div {{ $attributes->merge(['class' => $erste ? 'pt-2' : 'mt-6 border-t border-gray-100 pt-5 dark:border-gray-700']) }}>
    <div class="mb-1 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-cerulean-700 dark:text-cerulean-400">
            {{ $titel }}
        </h3>

        @if ($hinweis)
            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $hinweis }}</span>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
        {{ $slot }}
    </div>
</div>
