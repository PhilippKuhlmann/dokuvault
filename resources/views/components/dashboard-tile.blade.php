{{--
    One tile on the customer dashboard: heading on top, content below.

    Fixed height, the content scrolls: before, every tile grew with its list
    and the five stood in two different grids - licenses next to an empty
    certificates box, contacts four times as tall as sites. Same geometry for
    all, whatever is in them.

    -mx-2 px-2 on the scroll area: the rows pull themselves outwards for their
    hover background (-mx-2), and overflow-y-auto clips horizontally as well -
    without the extra room the hover would be cut off at the edge.
--}}
@props(['title'])

<x-panel {{ $attributes->merge(['class' => 'flex h-96 flex-col']) }}>
    <div class="mb-4 shrink-0 text-2xl font-CoconPro text-gray-900 dark:text-gray-100">{{ $title }}</div>
    <div class="-mx-2 min-h-0 flex-1 overflow-y-auto px-2">
        {{ $slot }}
    </div>
</x-panel>
