{{--
    Rackserver, 1 HE, mit acht 2,5"-Schaechten.

    Woran man ihn erkennt: die Reihe schmaler Laufwerksschaechte, jeder mit
    Griff und zwei Leuchten, links das Bedienfeld mit dem Einschalter und der
    Ausziehlasche fuer das Typenschild.
--}}
@php
    $schacht = 66;
    $start = 190;
    $sh = min(64, $h - 24);
@endphp

{{-- Bedienfeld links: Einschalter, USB, Ausziehlasche --}}
<circle cx="104" cy="{{ $mid }}" r="16" fill="none" stroke="{{ $ink }}" stroke-opacity="0.55" stroke-width="3" />
<line x1="104" y1="{{ $mid - 15 }}" x2="104" y2="{{ $mid - 4 }}" stroke="{{ $ink }}" stroke-opacity="0.55" stroke-width="3" />
<rect x="132" y="{{ $mid - 10 }}" width="22" height="20" rx="2" fill="{{ $ink }}" fill-opacity="0.3" />
<rect x="{{ $start - 22 }}" y="{{ $mid - $sh / 2 }}" width="12" height="{{ $sh }}" rx="3"
    fill="{{ $ink }}" fill-opacity="0.22" />

{{-- Acht Laufwerksschaechte --}}
@for ($i = 0; $i < 8; $i++)
    @php $x = $start + $i * $schacht; @endphp
    <rect x="{{ $x }}" y="{{ $mid - $sh / 2 }}" width="{{ $schacht - 8 }}" height="{{ $sh }}" rx="3"
        fill="{{ $ink }}" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
    {{-- Griffmulde --}}
    <rect x="{{ $x + 8 }}" y="{{ $mid - 8 }}" width="{{ $schacht - 34 }}" height="16" rx="3"
        fill="{{ $ink }}" fill-opacity="0.24" />
    {{-- Betrieb gruen, Aktivitaet daneben --}}
    <circle cx="{{ $x + $schacht - 20 }}" cy="{{ $mid - 16 }}" r="4" fill="#22c55e" fill-opacity="0.85" />
    <circle cx="{{ $x + $schacht - 20 }}" cy="{{ $mid + 16 }}" r="4" fill="{{ $ink }}" fill-opacity="{{ $i % 2 ? 0.45 : 0.2 }}" />
@endfor

{{-- Rechts das Typenschild und ein zweiter USB-Anschluss --}}
<rect x="{{ $start + 8 * $schacht + 10 }}" y="{{ $mid - 18 }}" width="70" height="36" rx="3"
    fill="{{ $ink }}" fill-opacity="0.14" stroke="{{ $ink }}" stroke-opacity="0.35" stroke-width="2" />
