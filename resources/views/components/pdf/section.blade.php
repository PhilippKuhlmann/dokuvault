@props([
    'title',
    'items',
    'titleField' => 'name',
    'groups' => [],
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
            </div>
        </div>
    @empty
        <div class="empty">— keine Einträge —</div>
    @endforelse
</div>
