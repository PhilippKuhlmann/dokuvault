{{--
    UniFi Switch Pro 48 PoE, 1 HE.

    Wie der 24er, nur enger: 48 Buchsen in zwei Reihen zu vierundzwanzig und
    vier Modulschaechte statt zwei. Bewusst eine eigene Datei und keine
    Schleife mit Parameter - die Masse sind andere, und eine Zeichnung, die
    beides kann, kann am Ende keines von beidem gut.
--}}
@php
    $pw = 17; $ph = 26; $ab = 2;
    $start = 200;
    $gruppe = 8;
@endphp

<rect x="66" y="{{ $mid - 34 }}" width="118" height="68" rx="6"
    fill="{{ $ink }}" fill-opacity="0.22" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
<circle cx="103" cy="{{ $mid - 4 }}" r="19" fill="none" stroke="{{ $ink }}" stroke-opacity="0.55" stroke-width="3" />
<circle cx="103" cy="{{ $mid - 4 }}" r="7" fill="{{ $ink }}" fill-opacity="0.45" />
<rect x="134" y="{{ $mid - 20 }}" width="34" height="24" rx="3" fill="{{ $ink }}" fill-opacity="0.3" />
<rect x="76" y="{{ $mid + 22 }}" width="72" height="6" rx="3" fill="{{ $ink }}" fill-opacity="0.35" />

@for ($i = 0; $i < 48; $i++)
    @php
        $reihe = $i % 2;
        $spalte = intdiv($i, 2);
        $x = $start + $spalte * ($pw + $ab) + intdiv($spalte, 4) * $gruppe;
        $y = $mid - $ph - 4 + $reihe * ($ph + 8);
    @endphp
    <rect data-port="{{ $i + 1 }}" x="{{ $x }}" y="{{ $y }}" width="{{ $pw }}" height="{{ $ph }}" rx="2"
        fill="#22c55e" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="1.5" />
    <rect x="{{ $x + $pw / 2 - 4 }}" y="{{ $y + $ph - 6 }}" width="8" height="6" fill="{{ $ink }}" fill-opacity="0.4" />
    <circle cx="{{ $x + 4 }}" cy="{{ $y + 5 }}" r="2" fill="#22c55e" fill-opacity="{{ $i % 3 ? 0.85 : 0.25 }}" />
@endfor

@for ($i = 0; $i < 4; $i++)
    @php
        $y = $mid - $ph - 4 + ($i % 2) * ($ph + 8);
        $x = 918 + intdiv($i, 2) * 50;
    @endphp
    <rect data-sfp="{{ $i + 1 }}" x="{{ $x }}" y="{{ $y }}" width="44" height="{{ $ph }}" rx="3"
        fill="{{ $ink }}" fill-opacity="0.12" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="2" />
    <rect x="{{ $x + 7 }}" y="{{ $y + $ph / 2 - 4 }}" width="30" height="8" rx="2" fill="{{ $ink }}" fill-opacity="0.35" />
@endfor

<rect x="{{ $start }}" y="{{ $mid - $ph - 14 }}" width="{{ 962 - $start }}" height="5" rx="2"
    fill="{{ $ink }}" fill-opacity="0.2" />
