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

<x-rack.chassis>
    {{-- Spalte 1 ist die HE-Skala wie im Schema, damit sich beide Ansichten
         zeilenweise vergleichen lassen. Spalten 2 und 4 sind die
         Montageschienen, Spalte 3 nimmt die Blenden auf.
         Ohne Zeilenabstand: sonst trennt eine Haarlinie auch zwei benachbarte
         freie Hoeheneinheiten, und die Luecke sieht gefaechert aus statt leer.
         Blenden bleiben trotzdem unterscheidbar, sie haben eigene Raender -
         im echten Schrank sitzen Geraete ohnehin buendig aufeinander. --}}
    <div class="grid" style="grid-template-columns: 2.5rem 0.6rem 1fr 0.6rem;">

        {{-- HE-Skala links (identisch zum Schema) --}}
        @for ($u = $he; $u >= 1; $u--)
            <div class="flex items-center justify-end pr-2 text-[11px] font-mono text-gray-400 dark:text-gray-500"
                style="grid-column: 1; grid-row: {{ $he - $u + 1 }}; min-height: {{ $rowHeight }};">{{ $u }}</div>
        @endfor

        {{-- Montageschienen mit Lochung, je Hoeheneinheit ein Loch --}}
        @foreach ([2, 4] as $col)
            @for ($u = $he; $u >= 1; $u--)
                <div style="grid-column: {{ $col }}; grid-row: {{ $he - $u + 1 }}; min-height: {{ $rowHeight }};"
                    class="flex items-center justify-center bg-gray-300 dark:bg-gray-800">
                    <span class="block h-1 w-1 rounded-[1px] bg-gray-500/70 dark:bg-gray-600"></span>
                </div>
            @endfor
        @endforeach

        {{-- Leere Hoeheneinheiten: offener Einschub. Bewusst voellig flach -
             kein Schatten, keine Kante. Wo nichts eingebaut ist, soll auch
             nichts angedeutet werden; jede Schattierung liest sich als Blech.
             Unterscheidbar bleibt es ueber die Blende daneben, die Rahmen,
             Schrauben und eine graue Flaeche hat. --}}
        @for ($u = $he; $u >= 1; $u--)
            @unless ($occupied[$u] ?? false)
                <div data-empty="{{ $u }}"
                    style="grid-column: 3; grid-row: {{ $he - $u + 1 }}; min-height: {{ $rowHeight }};"
                    class="bg-white dark:bg-gray-800"></div>
            @endunless
        @endfor

        {{-- Einbauten als gezeichnete Blenden --}}
        @foreach ($rack->items as $item)
            @php
                $key = $item->device_type ? ($typeKeys[$item->device_type] ?? null) : null;
                $color = $item->device_type ? ($colors[$key] ?? $deviceDefault) : $passive;
            @endphp
            <div wire:key="rack-face-{{ $item->id }}"
                style="grid-column: 3; grid-row: {{ $he - $item->topUnit() + 1 }} / span {{ $item->height_units }}; min-height: {{ $rowHeight }};"
                class="{{ $color }}"
                title="{{ $item->label() }} · {{ $item->height_units }} HE">
                <x-rack.face :appearance="$item->faceAppearance()" :he="$item->height_units" />
            </div>
        @endforeach

    </div>
</x-rack.chassis>
