{{--
    Gezeichnete Frontansicht desselben Racks - ohne Beschriftung, so wie der
    Schrank tatsaechlich aussieht. Erwartet: $rack (mit items.device geladen).

    Bewusst nicht interaktiv: gearbeitet wird im beschrifteten Schema daneben,
    diese Ansicht dient dem Wiedererkennen vor Ort.
--}}
@php
    $he = $rack->height_units;
    $rowHeight = '2rem';

    // Farbe je Geraetetyp - dieselbe Zuordnung wie im Schema, damit sich beide
    // Ansichten nebeneinander lesen lassen.
    $typeKeys = collect(config('custom.rack_device_types'))->map(fn ($v) => $v[0])->flip();
    $colors = [
        'server' => 'text-sky-700 dark:text-sky-300',
        'networkswitch' => 'text-emerald-700 dark:text-emerald-300',
        'nas' => 'text-amber-700 dark:text-amber-300',
        'router' => 'text-violet-700 dark:text-violet-300',
        'ups' => 'text-rose-700 dark:text-rose-300',
    ];
    $deviceDefault = 'text-cerulean-700 dark:text-cerulean-300';
    $passive = 'text-gray-500 dark:text-gray-400';

    $occupied = [];
    foreach ($rack->items as $item) {
        for ($u = $item->position; $u <= $item->topUnit(); $u++) {
            $occupied[$u] = true;
        }
    }
@endphp

<div class="inline-block min-w-64 w-full max-w-md rounded-lg border-2 border-gray-400 bg-gray-200 p-2 dark:border-gray-600 dark:bg-gray-950">
    {{-- Schmale Spalten links/rechts sind die Montageschienen --}}
    <div class="grid gap-y-px" style="grid-template-columns: 0.6rem 1fr 0.6rem;">

        {{-- Montageschienen mit Lochung, je Hoeheneinheit ein Loch --}}
        @foreach ([1, 3] as $col)
            @for ($u = $he; $u >= 1; $u--)
                <div style="grid-column: {{ $col }}; grid-row: {{ $he - $u + 1 }}; min-height: {{ $rowHeight }};"
                    class="flex items-center justify-center bg-gray-300 dark:bg-gray-800">
                    <span class="block h-1 w-1 rounded-[1px] bg-gray-500/70 dark:bg-gray-600"></span>
                </div>
            @endfor
        @endforeach

        {{-- Leere Hoeheneinheiten: offener Einschub, ohne Blende.
             Hell = weiss, dunkel = schwarz. Die Schattenkante innen gibt der
             Luecke Tiefe und haelt sie von einer Blindplatte unterscheidbar -
             die hat Rahmen, Schrauben und eine graue Flaeche. --}}
        @for ($u = $he; $u >= 1; $u--)
            @unless ($occupied[$u] ?? false)
                <div style="grid-column: 2; grid-row: {{ $he - $u + 1 }}; min-height: {{ $rowHeight }};"
                    class="bg-white shadow-[inset_0_2px_5px_rgba(0,0,0,0.16)] dark:bg-black dark:shadow-[inset_0_2px_6px_rgba(0,0,0,0.75)]"></div>
            @endunless
        @endfor

        {{-- Einbauten als gezeichnete Blenden --}}
        @foreach ($rack->items as $item)
            @php
                $key = $item->device_type ? ($typeKeys[$item->device_type] ?? null) : null;
                $color = $item->device_type ? ($colors[$key] ?? $deviceDefault) : $passive;
            @endphp
            <div wire:key="rack-face-{{ $item->id }}"
                style="grid-column: 2; grid-row: {{ $he - $item->topUnit() + 1 }} / span {{ $item->height_units }}; min-height: {{ $rowHeight }};"
                class="{{ $color }}"
                title="{{ $item->label() }} · {{ $item->height_units }} HE">
                <x-rack.face :appearance="$item->faceAppearance()" :he="$item->height_units" />
            </div>
        @endforeach

    </div>
</div>
