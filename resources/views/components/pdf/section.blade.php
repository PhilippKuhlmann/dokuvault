@props([
    'title',
    'items',
    'titleField' => 'name',
    'groups' => [],
])

@php
    $count = count($groups);
    // Computed from the number of groups instead of in steps: the old case
    // analysis gave four groups 30 percent each - 120 in total, so the fourth
    // column wrapped. "Internet / WAN" already had four.
    $width = $count <= 1 ? 97 : intdiv(94, $count);

    // Only what the person who ordered the export may see in the browser -
    // the PDF holds the passwords of every kind of object. Without an owner
    // (pdfOwner, shared by KundenPdfErzeugen) nothing is shown.
    $first = collect($items)->first();
    $allowed = $first === null
        || (($pdfOwner ?? null) && \Illuminate\Support\Facades\Gate::forUser($pdfOwner)->allows('viewAny', $first::class));
@endphp

@if ($allowed)
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
@endif
