{{--
    Rack-USV, 2 HE.

    Das Gesicht einer USV ist ihr Display: die Balkenanzeige fuer Last und
    Ladung, daneben die vier Tasten. Rechts die Lueftung.

    Zeichnet ueber die volle Hoehe ($h), nicht je Hoeheneinheit - eine USV mit
    zwei HE ist ein Geraet, keine zwei gestapelten.
--}}
@php
    $links = 80;
    $displayBreite = 300;
    $displayHoehe = min(78, $h - 30);
@endphp

{{-- Display --}}
<rect x="{{ $links }}" y="{{ $mid - $displayHoehe / 2 }}" width="{{ $displayBreite }}" height="{{ $displayHoehe }}" rx="6"
    fill="{{ $ink }}" fill-opacity="0.26" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="2" />

{{-- Balkenanzeige: links Last, rechts Ladung - zwei Reihen zu je fuenf Segmenten --}}
@for ($reihe = 0; $reihe < 2; $reihe++)
    @for ($i = 0; $i < 5; $i++)
        @php
            $x = $links + 22 + $i * 26 + $reihe * 150;
            $y = $mid - 16;
            // Die Ladung steht voll, die Last bei drei Vierteln - so sieht eine
            // USV im Betrieb aus.
            $an = $reihe === 1 || $i < 4;
        @endphp
        <rect x="{{ $x }}" y="{{ $y }}" width="18" height="32" rx="2"
            fill="{{ $an ? '#22c55e' : $ink }}" fill-opacity="{{ $an ? 0.75 : 0.25 }}" />
    @endfor
@endfor
<rect x="{{ $links + 22 }}" y="{{ $mid + 24 }}" width="126" height="5" rx="2" fill="{{ $ink }}" fill-opacity="0.3" />
<rect x="{{ $links + 172 }}" y="{{ $mid + 24 }}" width="126" height="5" rx="2" fill="{{ $ink }}" fill-opacity="0.3" />

{{-- Vier Tasten rechts vom Display --}}
@for ($i = 0; $i < 4; $i++)
    @php $cx = $links + $displayBreite + 46 + $i * 60; @endphp
    <circle cx="{{ $cx }}" cy="{{ $mid }}" r="19"
        fill="{{ $ink }}" fill-opacity="0.14" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="2" />
    <circle cx="{{ $cx }}" cy="{{ $mid }}" r="7" fill="{{ $ink }}" fill-opacity="0.35" />
@endfor

{{-- Statusleuchte: gruen heisst Netzbetrieb --}}
<circle cx="{{ $links + $displayBreite + 20 }}" cy="{{ $mid }}" r="8" fill="#22c55e" fill-opacity="0.85" />

{{-- Lueftungsschlitze rechts --}}
@for ($i = 0; $i < 16; $i++)
    <rect x="{{ 700 + $i * 20 }}" y="{{ 20 }}" width="10" height="{{ $h - 40 }}" rx="5"
        fill="{{ $ink }}" fill-opacity="0.16" />
@endfor
