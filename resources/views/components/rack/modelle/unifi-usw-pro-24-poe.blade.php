{{--
    UniFi Switch Pro 24 PoE, 1 HE.

    Gezeichnet innerhalb des SVG von x-rack.face - Grundplatte, Kanten und
    Montageohren stehen dort schon. Hier kommt nur das Gesicht dazu:
    Bedienfeld links, 24 Buchsen in zwei Reihen zu zwoelf mit einer Luecke
    nach je acht, rechts zwei Modulschaechte.

    Verfuegbar: $ink (Zeichenfarbe), $h (Hoehe), $he, $mid (Mitte), $inset.
--}}
@php
    $pw = 33; $ph = 26; $ab = 3;
    $start = 200;
    $gruppe = 10;
@endphp

{{-- Bedienfeld: das runde Feld mit dem Ring ist das, woran man einen Pro
     erkennt. Es sitzt links, nicht rechts. --}}
<rect x="66" y="{{ $mid - 34 }}" width="118" height="68" rx="6"
    fill="{{ $ink }}" fill-opacity="0.22" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
<circle cx="103" cy="{{ $mid - 4 }}" r="19" fill="none" stroke="{{ $ink }}" stroke-opacity="0.55" stroke-width="3" />
<circle cx="103" cy="{{ $mid - 4 }}" r="7" fill="{{ $ink }}" fill-opacity="0.45" />
<rect x="134" y="{{ $mid - 20 }}" width="34" height="24" rx="3" fill="{{ $ink }}" fill-opacity="0.3" />
<rect x="76" y="{{ $mid + 22 }}" width="72" height="6" rx="3" fill="{{ $ink }}" fill-opacity="0.35" />

{{-- Buchsen: zwei Reihen, Luecke nach je acht Ports --}}
@for ($i = 0; $i < 24; $i++)
    @php
        $reihe = $i % 2;                    // im Schrank sind die Ports gepaart
        $spalte = intdiv($i, 2);
        $x = $start + $spalte * ($pw + $ab) + intdiv($spalte, 4) * $gruppe;
        $y = $mid - $ph - 4 + $reihe * ($ph + 8);
    @endphp
    <rect data-port="{{ $i + 1 }}" x="{{ $x }}" y="{{ $y }}" width="{{ $pw }}" height="{{ $ph }}" rx="3"
        fill="#22c55e" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="2" />
    <rect x="{{ $x + $pw / 2 - 7 }}" y="{{ $y + $ph - 7 }}" width="14" height="7" fill="{{ $ink }}" fill-opacity="0.4" />
    <circle cx="{{ $x + 6 }}" cy="{{ $y + 6 }}" r="2.5" fill="#22c55e" fill-opacity="{{ $i % 3 ? 0.85 : 0.25 }}" />
@endfor

{{-- Zwei Modulschaechte rechts, uebereinander --}}
@for ($i = 0; $i < 2; $i++)
    @php $y = $mid - $ph - 4 + $i * ($ph + 8); @endphp
    <rect data-sfp="{{ $i + 1 }}" x="944" y="{{ $y }}" width="46" height="{{ $ph }}" rx="3"
        fill="{{ $ink }}" fill-opacity="0.12" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="2" />
    <rect x="952" y="{{ $y + $ph / 2 - 4 }}" width="30" height="8" rx="2" fill="{{ $ink }}" fill-opacity="0.35" />
@endfor

{{-- Beschriftungsstreifen ueber den Ports, wie am Geraet --}}
<rect x="{{ $start }}" y="{{ $mid - $ph - 14 }}" width="{{ 990 - $start }}" height="5" rx="2"
    fill="{{ $ink }}" fill-opacity="0.2" />
