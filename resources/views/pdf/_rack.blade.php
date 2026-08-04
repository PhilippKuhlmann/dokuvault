{{--
    Eine Rackseite für den PDF-Export. Erwartet: $rack (mit items.device) und
    $seite ('front'|'rear').

    Bewusst als Tabelle mit rowspan statt als CSS-Grid: DomPDF beherrscht
    weder Grid noch Flexbox, Tabellen dagegen zuverlässig. Farben und Maße
    stehen inline - Tailwind-Klassen gibt es im PDF nicht.

    Die Blenden kommen als <img> auf eine kurzlebige SVG-Datei. DomPDF rendert
    SVG weder inline im HTML noch als Daten-URI - nur als Bilddatei innerhalb
    seines chroot (dem Projektverzeichnis). Den Ordner legt CustomerController
    ::viewPDF an und löscht ihn nach dem Rendern wieder.

    Erwartet zusätzlich: $svgDir (absoluter Pfad).
--}}
@php
    $seite = $seite ?? 'front';
    $he = $rack->height_units;

    $typeKeys = collect(config('custom.rack_device_types'))->map(fn ($v) => $v[0])->flip();

    // Druckfarben: kräftig genug für Papier, ohne Flächen zu schwärzen.
    $tints = [
        'server' => '#0369a1',
        'networkswitch' => '#047857',
        'nas' => '#b45309',
        'router' => '#6d28d9',
        'ups' => '#be123c',
    ];
    $deviceDefault = '#1d4ed8';
    $passiveTint = '#4b5563';
    $plate = '#e5e7eb';

    // Oberste belegte HE => Einbau; alle übrigen belegten HE nur merken,
    // damit dort keine eigene Zeile entsteht (die deckt der rowspan ab).
    $startsAt = [];
    $covered = [];
    foreach ($rack->itemsFuerSeite($seite) as $item) {
        $startsAt[$item->topUnit()] = $item;
        for ($u = $item->position; $u < $item->topUnit(); $u++) {
            $covered[$u] = true;
        }
    }

    $rowHeight = 16;   // px je Höheneinheit im PDF
@endphp

<table class="rackview" cellspacing="0" cellpadding="0">
    @for ($u = $he; $u >= 1; $u--)
        <tr>
            <td class="rackview-scale">{{ $u }}</td>

            @if (isset($startsAt[$u]))
                @php
                    $item = $startsAt[$u];
                    $key = $item->device_type ? ($typeKeys[$item->device_type] ?? null) : null;
                    $tint = $item->device_type ? ($tints[$key] ?? $deviceDefault) : $passiveTint;
                    $slotHeight = $rowHeight * $item->height_units;

                    $svg = view('components.rack.face', [
                        'appearance' => $item->faceAppearance(),
                        'he' => $item->height_units,
                        'ports' => $item->device?->port_count,
                        'plate' => $plate,
                        'tint' => $tint,
                        'width' => 330,
                        'height' => $slotHeight,
                    ])->render();

                    $svgFile = $svgDir . '/item-' . $item->id . '.svg';
                    file_put_contents($svgFile, $svg);
                @endphp
                <td class="rackview-slot" rowspan="{{ $item->height_units }}">
                    <img src="{{ $svgFile }}" width="330" height="{{ $slotHeight }}"
                        alt="{{ $item->label() }}">
                </td>
            @elseif (! isset($covered[$u]))
                <td class="rackview-empty">&nbsp;</td>
            @endif
        </tr>
    @endfor
</table>
