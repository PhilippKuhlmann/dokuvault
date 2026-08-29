@props([
    'appearance' => 'blank',
    'he' => 1,
    // Adresse eines hinterlegten Fotos; ohne eines wird gezeichnet.
    'image' => null,
    // Hoehe des gezeigten Schranks. Acht Hoeheneinheiten, weil kein
    // Katalogelement hoeher werden darf (RackCatalogItemRequest::MAX_HE) -
    // so ist am Bild ablesbar, wie viel vom Schrank das Element einnimmt.
    'hoehe' => 8,
])

{{--
    Ein kleiner Schrank mit dem Element ganz unten eingebaut.

    Dieselbe Bauweise wie die echte Frontansicht (rack/_rackview): HE-Skala,
    zwei Montageschienen, dazwischen die Blende. Bewusst nicht schematischer -
    die Vorschau soll zeigen, wie der Einbau spaeter tatsaechlich aussieht,
    und nicht ein zweites Bild sein, das man erst uebersetzen muss.
--}}
@php
    $he = max(1, min((int) $he, $hoehe));
    $zeile = '1.75rem';
@endphp

<x-rack.chassis class="max-w-sm">
    <div class="grid" style="grid-template-columns: 1.75rem 0.5rem 1fr 0.5rem;">

        {{-- HE-Skala links, von unten gezaehlt wie im echten Schrank --}}
        @for ($u = $hoehe; $u >= 1; $u--)
            <div class="flex items-center justify-end pr-1.5 text-[10px] font-mono text-gray-400 dark:text-gray-500"
                style="grid-column: 1; grid-row: {{ $hoehe - $u + 1 }}; min-height: {{ $zeile }};">{{ $u }}</div>
        @endfor

        {{-- Montageschienen mit Lochung --}}
        @foreach ([2, 4] as $spalte)
            @for ($u = $hoehe; $u >= 1; $u--)
                <div style="grid-column: {{ $spalte }}; grid-row: {{ $hoehe - $u + 1 }}; min-height: {{ $zeile }};"
                    class="flex items-center justify-center bg-gray-300 dark:bg-gray-800">
                    <span class="block h-1 w-1 rounded-[1px] bg-gray-500/70 dark:bg-gray-600"></span>
                </div>
            @endfor
        @endforeach

        {{-- Freie Hoeheneinheiten ueber dem Einbau: offener Einschub --}}
        @for ($u = $hoehe; $u > $he; $u--)
            <div style="grid-column: 3; grid-row: {{ $hoehe - $u + 1 }}; min-height: {{ $zeile }};"
                class="bg-white dark:bg-gray-800"></div>
        @endfor

        {{-- Das Element selbst, ganz unten und ueber $he Einheiten --}}
        <div style="grid-column: 3; grid-row: {{ $hoehe - $he + 1 }} / span {{ $he }}; min-height: {{ $zeile }};"
            class="text-gray-500 dark:text-gray-400">
            <x-rack.face :appearance="$appearance" :he="$he" :image="$image" />
        </div>

    </div>
</x-rack.chassis>
