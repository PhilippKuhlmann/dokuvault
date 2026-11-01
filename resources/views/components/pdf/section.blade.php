@props([
    'title',
    'items',
    'titleField' => 'name',
    'groups' => [],
])

@php
    // Only what the person who ordered the export may see in the browser -
    // the PDF holds the passwords of every kind of object. Without an owner
    // (pdfOwner, shared by GenerateCustomerPdf) nothing is shown.
    $first = collect($items)->first();
    $allowed = $first === null
        || (($pdfOwner ?? null) && \Illuminate\Support\Facades\Gate::forUser($pdfOwner)->allows('viewAny', $first::class));

    $value = fn ($field, $item) => $field instanceof \Closure ? $field($item) : data_get($item, $field);

    // A field without a value is left out instead of printed as a bare label.
    // In the device sections that was half the rows - "Notiz", "Passwort" and
    // "Port" stood there empty on every single card. 0 is a value and stays
    // ("Einbauten" counts items), only null and the empty string go.
    $filled = function (array $fields, $item) use ($value) {
        $out = [];

        foreach ($fields as $label => $field) {
            $v = $value($field, $item);

            if ($v !== null && (! is_string($v) || trim($v) !== '')) {
                $out[$label] = $v;
            }
        }

        return $out;
    };
@endphp

@if ($allowed)
<div class="section">
    {{-- data-toc is read while rendering: it tells the job which page this
         section starts on, for the table of contents and the running head. --}}
    <div class="heading" data-toc="{{ $title }}">{{ $title }}</div>

    @forelse ($items as $item)
        @php
            // Only the groups that hold something. An empty one cannot stay
            // behind as a placeholder to keep the columns of all cards on the
            // same lines: DomPDF collapses a float without content to zero
            // width, and everything after it slid into the wrong column.
            $gruppen = collect($groups)->map(fn ($fields) => $filled($fields, $item))->filter(fn ($g) => count($g) > 0);

            // Zwei Spalten, der Rest bricht in die naechste Zeile um. Vier
            // Gruppen nebeneinander liessen jeder 23 Prozent des Blattes, und
            // so schmal passt "Vertragsnummer" nicht mehr neben seinen Wert:
            // entweder brach die Beschriftung im Wort, oder der Wert trat aus
            // seinem Kasten und lief quer ueber die Nachbarspalte.
        @endphp
        <div class="card">
            <div class="card-title">{{ $titleField instanceof \Closure ? $titleField($item) : data_get($item, $titleField) }}</div>
            <div class="card-body">
                {{-- Die Gruppen stehen in einer Tabelle, nicht als Floats.
                     DomPDF setzt clear auf einem Float nicht um: die dritte
                     Gruppe rutschte nicht unter die erste, sondern darueber.
                     Aus demselben Grund ist die Rack-Ansicht eine Tabelle. --}}
                <table class="groups">
                    @foreach ($gruppen->chunk(2) as $paar)
                        <tr>
                            @foreach ($paar as $groupTitle => $felder)
                                <td class="group-cell">
                                    <div class="card-table-title">{{ __($groupTitle) }}</div>
                                    <table>
                                        @foreach ($felder as $label => $v)
                                            <tr>
                                                <td class="key">{{ __($label) }}</td>
                                                <td class="val">{{ $v }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </td>
                            @endforeach

                            {{-- Ungerade Zahl Gruppen: die letzte Zeile braucht
                                 eine zweite Zelle, sonst zieht sich die erste
                                 ueber die ganze Breite. --}}
                            @if ($paar->count() === 1 && $gruppen->count() > 1)
                                <td class="group-cell"></td>
                            @endif
                        </tr>
                    @endforeach
                </table>

                @if ($gruppen->isEmpty())
                    <div class="empty-card">{{ __('keine Angaben') }}</div>
                @endif
            </div>
        </div>
    @empty
        <div class="empty">— {{ __('keine Einträge') }} —</div>
    @endforelse
</div>
@endif
