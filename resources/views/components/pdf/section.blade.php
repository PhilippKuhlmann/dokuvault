@props([
    'title',
    'items',
    'titleField' => 'name',
    'groups' => [],
    // Eine Liste, die an jedem Eintrag haengt - etwa die Zugaenge eines
    // FTP-Servers. Ohne sie steht der Server im PDF ohne seine Benutzer da.
    'unterliste' => null,
])

@php
    $count = count($groups);
    // Aus der Gruppenzahl gerechnet statt in Stufen: Mit der alten
    // Fallunterscheidung bekamen vier Gruppen je 30 Prozent - zusammen 120,
    // also brach die vierte Spalte um. Betraf schon "Internet / WAN".
    $width = $count <= 1 ? 97 : intdiv(94, $count);
@endphp

<div class="section">
    <div class="heading">{{ $title }}</div>

    @forelse ($items as $item)
        <div class="card">
            <div class="card-title">{{ $titleField instanceof \Closure ? $titleField($item) : data_get($item, $titleField) }}</div>
            <div class="card-body">
                @foreach ($groups as $groupTitle => $fields)
                    <div class="card-table" style="width: {{ $width }}%;">
                        <div class="card-table-title">{{ __($groupTitle) }}</div>
                        <table>
                            @foreach ($fields as $label => $field)
                                <tr>
                                    <td class="key">{{ __($label) }}</td>
                                    <td class="val">{{ $field instanceof \Closure ? $field($item) : data_get($item, $field) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endforeach
                <div class="clear"></div>

                @if ($unterliste)
                    @php ($zeilen = data_get($item, $unterliste['relation']) ?? collect())
                    @if (count($zeilen))
                        <div class="card-table" style="width: 97%;">
                            <div class="card-table-title">{{ __($unterliste['titel']) }}</div>
                            <table>
                                <tr>
                                    @foreach (array_keys($unterliste['spalten']) as $ueberschrift)
                                        <td class="key">{{ __($ueberschrift) }}</td>
                                    @endforeach
                                </tr>
                                @foreach ($zeilen as $zeile)
                                    <tr>
                                        @foreach ($unterliste['spalten'] as $feld)
                                            <td class="val">{{ data_get($zeile, $feld) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                        <div class="clear"></div>
                    @endif
                @endif
            </div>
        </div>
    @empty
        <div class="empty">— keine Einträge —</div>
    @endforelse
</div>
