{{--
    One column of an opened card in the fixed grid (x-card raster): a head
    with symbol (Zugang, Gerät, System), below it the blocks of that theme.
    Every block keeps its own small heading; the column only sorts them.

    The tables of the blocks get the same label width here (42 %): side by
    side the values stand flush, instead of each block measuring its own.
    The fixed table also lets a long value break between words: break-all
    (agent blocks, made for tables of free width) split "zugewi-esen".
--}}
@props(['titel', 'symbol' => null])

{{-- Every block leaves itself out when it has nothing (minitablecard); a
     column whose blocks all did is left out too - a heading over nothing
     looked like missing data. Livewire's BLOCK markers are not content. --}}
@if (trim(preg_replace('/<!--.*?-->/s', '', (string) $slot)) !== '')
<section {{ $attributes->merge(['class' => 'min-w-0 [&_table]:w-full [&_table]:table-fixed [&_td:first-child]:w-[42%] [&_td+td]:w-auto [&_td]:[word-break:normal] [&_td]:wrap-break-word']) }}>
    <h3 class="mb-3 flex items-center gap-2 border-b border-gray-200 pb-1.5 text-xs font-semibold uppercase tracking-wide text-chathams-blue-700 dark:border-gray-700 dark:text-cerulean-300">
        @if ($symbol)
            <x-dynamic-component :component="$symbol" class="h-4 w-4" />
        @endif
        {{ $titel }}
    </h3>
    {{ $slot }}
</section>
@endif
