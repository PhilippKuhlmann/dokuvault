@props([
    'title',
    'items',
    'titleField' => 'name',
    'groups' => [],
])

@php
    $count = count($groups);
    $width = $count <= 1 ? 97 : ($count === 2 ? 47 : 30);
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
