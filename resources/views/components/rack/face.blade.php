@props([
    'appearance' => 'blank',
    'he' => 1,
    // Fuer den PDF-Export: DomPDF kennt weder Tailwind-Klassen noch
    // currentColor. Werden Farben und Masse explizit uebergeben, kommt die
    // Zeichnung ohne Stylesheet aus.
    'plate' => null,
    'tint' => null,
    // Tatsaechliche Portanzahl eines dokumentierten Patchfelds; ohne Angabe
    // zeichnet die Blende die uebliche 24er-Reihe.
    'ports' => null,
    'width' => null,
    'height' => null,
    // Eigenes Foto der Blende (Adresse). Ist eines hinterlegt, tritt es an die
    // Stelle der Zeichnung. Im PDF nicht benutzt: Dort geht die Blende als
    // SVG-Datei heraus, und in einer SVG-Datei laesst sich kein <img> auf eine
    // zweite Datei verschachteln - pdf/_rack.blade.php setzt das Foto selbst.
    'image' => null,
])

@if ($image)
    {{-- Auf die Zelle gestreckt wie die Zeichnung daneben (preserveAspectRatio
         ="none"): Die Zelle ist der Platz, den das Geraet im Schrank einnimmt -
         ein eingepasstes Bild liesse dort Luft, wo keine ist. --}}
    <img src="{{ $image }}" alt="" role="presentation"
        {{ $attributes->merge(['class' => 'block h-full w-full object-fill']) }}>
@else

{{--
    Gezeichnete Frontblende eines Rack-Einbaus, ohne Beschriftung.

    Das Bild passt sich der Hoehe an: Der viewBox ist 1086 x (100 * HE) - das
    entspricht den echten Proportionen einer 19"-Blende (482,6 mm breit,
    44,45 mm je HE). preserveAspectRatio="none" laesst die Zeichnung exakt die
    Zelle fuellen; die Abweichung zum echten Seitenverhaeltnis liegt unter 10 %
    und faellt nicht auf.

    Details werden je Hoeheneinheit wiederholt oder mittig ueber die volle
    Hoehe gelegt - je nachdem, was am echten Geraet auch so waere: ein
    Patchfeld mit 2 HE hat zwei Portreihen, ein Server mit 2 HE hoehere
    Schaechte.
--}}
@php
    $ink = $tint ?: 'currentColor';   // Lasur und alle Details
    $h = 100 * $he;         // Gesamthoehe im viewBox

    // Feste Masse => PDF-Modus. php-svg-lib (DomPDF) beachtet
    // preserveAspectRatio nicht und zeichnet sonst in Rohgroesse ueber den
    // Rahmen hinaus. Deshalb dort die Skalierung selbst uebernehmen: viewBox
    // in Zielgroesse, Inhalt per transform gestaucht.
    $scaled = $width && $height;
    $mid = $h / 2;
    $inset = 46;            // Breite der Montageohren links/rechts
@endphp

<svg xmlns="http://www.w3.org/2000/svg" role="presentation" aria-hidden="true"
    @if ($scaled)
        width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}"
    @else
        viewBox="0 0 1086 {{ $h }}" preserveAspectRatio="none"
    @endif
    {{ $attributes->merge(['class' => 'block h-full w-full']) }}>
@if ($scaled)<g transform="scale({{ round($width / 1086, 5) }}, {{ round($height / $h, 5) }})">@endif

    {{-- Grundplatte in zwei Lagen:
         1. undurchsichtiges Blech in Neutralgrau - damit eine Blende nie wie
            der offene (dunkle) Einschub daneben aussieht;
         2. duenne Lasur in der Typfarbe.
         Die Lasur bleibt bewusst schwach, sonst verschwinden die Details
         darueber (Ports, Schaechte) im gleichfarbigen Untergrund. --}}
    <rect x="1" y="1" width="1084" height="{{ $h - 2 }}" rx="7"
        @if ($plate) fill="{{ $plate }}" @else class="fill-gray-300 dark:fill-gray-800" @endif />
    <rect x="1" y="1" width="1084" height="{{ $h - 2 }}" rx="7" fill="{{ $ink }}" fill-opacity="0.14" />
    {{-- Abkantung: heller Grat oben, Schatten unten --}}
    <line x1="10" y1="4" x2="1076" y2="4" stroke="#ffffff" stroke-opacity="0.22" stroke-width="3" />
    <line x1="10" y1="{{ $h - 4 }}" x2="1076" y2="{{ $h - 4 }}" stroke="#000000" stroke-opacity="0.28" stroke-width="3" />
    <rect x="1" y="1" width="1084" height="{{ $h - 2 }}" rx="7" fill="none" stroke="{{ $ink }}" stroke-opacity="0.55" stroke-width="2" />

    {{-- Montageohren mit Schrauben, je Hoeheneinheit eine --}}
    @for ($u = 0; $u < $he; $u++)
        @php $cy = $u * 100 + 50; @endphp
        <circle cx="23" cy="{{ $cy }}" r="9" fill="none" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="3" />
        <circle cx="1063" cy="{{ $cy }}" r="9" fill="none" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="3" />
    @endfor
    <line x1="{{ $inset }}" y1="6" x2="{{ $inset }}" y2="{{ $h - 6 }}" stroke="{{ $ink }}" stroke-opacity="0.25" stroke-width="2" />
    <line x1="{{ 1086 - $inset }}" y1="6" x2="{{ 1086 - $inset }}" y2="{{ $h - 6 }}" stroke="{{ $ink }}" stroke-opacity="0.25" stroke-width="2" />

    @switch($appearance)

        @case('server')
        @case('nas')
            @php
                // Schaechte fuellen die Hoehe: 1 HE = eine Reihe, 2 HE = zwei Reihen usw.
                $bays = $appearance === 'nas' ? 4 : 6;
                $bayW = (700 - 70) / $bays;
            @endphp
            @for ($u = 0; $u < $he; $u++)
                @for ($i = 0; $i < $bays; $i++)
                    @php $x = 70 + $i * $bayW; $y = $u * 100 + 22; @endphp
                    <rect x="{{ $x + 3 }}" y="{{ $y }}" width="{{ $bayW - 6 }}" height="56" rx="4"
                        fill="{{ $ink }}" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.4" stroke-width="2" />
                    {{-- Griffmulde --}}
                    <rect x="{{ $x + 10 }}" y="{{ $y + 20 }}" width="{{ $bayW - 34 }}" height="16" rx="3"
                        fill="{{ $ink }}" fill-opacity="0.22" />
                    {{-- Aktivitaets-LED --}}
                    <circle cx="{{ $x + $bayW - 16 }}" cy="{{ $y + 28 }}" r="5" fill="#22c55e" fill-opacity="0.85" />
                @endfor
            @endfor
            {{-- Lueftungsgitter rechts --}}
            @for ($c = 0; $c < 14; $c++)
                <rect x="{{ 760 + $c * 17 }}" y="18" width="8" height="{{ $h - 36 }}" rx="4"
                    fill="{{ $ink }}" fill-opacity="0.16" />
            @endfor
            {{-- Power-Taste + Status --}}
            <circle cx="1020" cy="{{ $mid }}" r="13" fill="none" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="3" />
            <line x1="1020" y1="{{ $mid - 12 }}" x2="1020" y2="{{ $mid - 2 }}" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="3" />
            @break

        @case('switch')
            @php
                // 24 Ports: 1 HE = zwei Reihen zu 12, ab 2 HE mehr Platz je Reihe.
                $perRow = 12;
                $portW = 54; $portH = $he > 1 ? 34 : 26;
                $gapGroup = 22;
            @endphp
            @for ($row = 0; $row < 2; $row++)
                @for ($i = 0; $i < $perRow; $i++)
                    @php
                        $x = 70 + $i * ($portW + 4) + intdiv($i, 6) * $gapGroup;
                        $y = $mid - $portH - 6 + $row * ($portH + 8);
                    @endphp
                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $portW }}" height="{{ $portH }}" rx="3"
                        fill="{{ $ink }}" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
                    {{-- RJ45-Nase --}}
                    <rect x="{{ $x + $portW / 2 - 8 }}" y="{{ $y + $portH - 8 }}" width="16" height="8"
                        fill="{{ $ink }}" fill-opacity="0.35" />
                    <circle cx="{{ $x + 7 }}" cy="{{ $y + 6 }}" r="3" fill="#22c55e" fill-opacity="{{ $i % 3 ? 0.8 : 0.25 }}" />
                @endfor
            @endfor
            {{-- SFP-Uplinks --}}
            @for ($i = 0; $i < 2; $i++)
                <rect x="{{ 900 + $i * 78 }}" y="{{ $mid - 20 }}" width="66" height="40" rx="4"
                    fill="{{ $ink }}" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
                <rect x="{{ 908 + $i * 78 }}" y="{{ $mid - 10 }}" width="50" height="20" rx="2"
                    fill="{{ $ink }}" fill-opacity="0.3" />
            @endfor
            @break

        @case('router')
            @for ($i = 0; $i < 5; $i++)
                @php $x = 90 + $i * 66; @endphp
                <rect x="{{ $x }}" y="{{ $mid - 17 }}" width="54" height="34" rx="3"
                    fill="{{ $ink }}" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
                <rect x="{{ $x + 19 }}" y="{{ $mid + 9 }}" width="16" height="8" fill="{{ $ink }}" fill-opacity="0.35" />
                <circle cx="{{ $x + 7 }}" cy="{{ $mid - 11 }}" r="3" fill="#22c55e" fill-opacity="0.8" />
            @endfor
            {{-- Konsolenport --}}
            <rect x="470" y="{{ $mid - 14 }}" width="70" height="28" rx="14"
                fill="{{ $ink }}" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
            {{-- Statusleuchten --}}
            @for ($i = 0; $i < 4; $i++)
                <circle cx="{{ 620 + $i * 34 }}" cy="{{ $mid }}" r="8"
                    fill="{{ $i === 0 ? '#22c55e' : $ink }}" fill-opacity="{{ $i === 0 ? 0.85 : 0.25 }}" />
            @endfor
            <rect x="800" y="{{ $mid - 22 }}" width="220" height="44" rx="6"
                fill="{{ $ink }}" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.35" stroke-width="2" />
            @break

        @case('ups')
            {{-- Display --}}
            <rect x="80" y="{{ $mid - min(34, $h / 2 - 12) }}" width="300" height="{{ min(68, $h - 24) }}" rx="6"
                fill="{{ $ink }}" fill-opacity="0.22" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
            @for ($i = 0; $i < 3; $i++)
                <rect x="{{ 100 + $i * 58 }}" y="{{ $mid - 8 }}" width="42" height="16" rx="2" fill="{{ $ink }}" fill-opacity="0.4" />
            @endfor
            {{-- Bedientasten --}}
            @for ($i = 0; $i < 2; $i++)
                <circle cx="{{ 430 + $i * 54 }}" cy="{{ $mid }}" r="16"
                    fill="{{ $ink }}" fill-opacity="0.12" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
            @endfor
            {{-- Lueftung --}}
            @for ($c = 0; $c < 18; $c++)
                <rect x="{{ 560 + $c * 26 }}" y="18" width="12" height="{{ $h - 36 }}" rx="6"
                    fill="{{ $ink }}" fill-opacity="0.16" />
            @endfor
            @break

        @case('patchpanel')
            @php
                // Eine Portreihe je Hoeheneinheit - wie beim echten 2-HE-Patchfeld.
                // Ein 48er-Feld mit 2 HE bekommt also zweimal 24 Ports.
                $proReihe = (int) ceil(($ports ?: 24) / max(1, $he));
                $pw = (1000 - 70) / $proReihe;
            @endphp
            @for ($u = 0; $u < $he; $u++)
                @php $cy = $u * 100 + 50; @endphp
                @for ($i = 0; $i < $proReihe; $i++)
                    @php $x = 70 + $i * $pw; @endphp
                    <rect x="{{ $x + 2 }}" y="{{ $cy - 20 }}" width="{{ $pw - 4 }}" height="40" rx="3"
                        fill="{{ $ink }}" fill-opacity="0.10" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
                    <rect x="{{ $x + $pw / 2 - 7 }}" y="{{ $cy + 12 }}" width="14" height="8" fill="{{ $ink }}" fill-opacity="0.35" />
                @endfor
                {{-- Beschriftungsstreifen (leer, ohne Text) --}}
                <rect x="70" y="{{ $cy + 26 }}" width="930" height="10" rx="2" fill="{{ $ink }}" fill-opacity="0.18" />
            @endfor
            @break

        @case('cablering')
            @for ($i = 0; $i < 5; $i++)
                @php $x = 130 + $i * 190; @endphp
                <path d="M {{ $x }} {{ $mid + 26 }} v -18 a 34 34 0 0 1 68 0 v 18"
                    fill="none" stroke="{{ $ink }}" stroke-opacity="0.5" stroke-width="7" stroke-linecap="round" />
            @endfor
            <line x1="{{ $inset + 10 }}" y1="{{ $mid + 30 }}" x2="{{ 1076 - $inset }}" y2="{{ $mid + 30 }}"
                stroke="{{ $ink }}" stroke-opacity="0.3" stroke-width="4" />
            @break

        @case('brush')
            {{-- Schlitz mit Buerstendichtung --}}
            <rect x="70" y="{{ $mid - 22 }}" width="930" height="44" rx="6"
                fill="{{ $ink }}" fill-opacity="0.28" stroke="{{ $ink }}" stroke-opacity="0.4" stroke-width="2" />
            @for ($i = 0; $i < 62; $i++)
                <line x1="{{ 80 + $i * 15 }}" y1="{{ $mid - 16 }}" x2="{{ 80 + $i * 15 }}" y2="{{ $mid + 16 }}"
                    stroke="{{ $ink }}" stroke-opacity="0.35" stroke-width="3" />
            @endfor
            @break

        @case('shelf')
            {{-- Frontkante eines Fachbodens: abgekantetes Blech --}}
            <rect x="{{ $inset + 8 }}" y="{{ $mid - 26 }}" width="{{ 1086 - 2 * $inset - 16 }}" height="52" rx="4"
                fill="{{ $ink }}" fill-opacity="0.20" stroke="{{ $ink }}" stroke-opacity="0.4" stroke-width="2" />
            <line x1="{{ $inset + 8 }}" y1="{{ $mid - 8 }}" x2="{{ 1078 - $inset }}" y2="{{ $mid - 8 }}"
                stroke="{{ $ink }}" stroke-opacity="0.28" stroke-width="3" />
            @for ($i = 0; $i < 3; $i++)
                <rect x="{{ 380 + $i * 120 }}" y="{{ $mid + 2 }}" width="70" height="12" rx="6"
                    fill="{{ $ink }}" fill-opacity="0.25" />
            @endfor
            @break

        @case('pdu')
            @for ($i = 0; $i < 8; $i++)
                @php $cx = 130 + $i * 115; @endphp
                <circle cx="{{ $cx }}" cy="{{ $mid }}" r="30"
                    fill="{{ $ink }}" fill-opacity="0.12" stroke="{{ $ink }}" stroke-opacity="0.45" stroke-width="2" />
                <circle cx="{{ $cx - 11 }}" cy="{{ $mid }}" r="5" fill="{{ $ink }}" fill-opacity="0.5" />
                <circle cx="{{ $cx + 11 }}" cy="{{ $mid }}" r="5" fill="{{ $ink }}" fill-opacity="0.5" />
            @endfor
            <rect x="1000" y="{{ $mid - 18 }}" width="36" height="36" rx="4"
                fill="#ef4444" fill-opacity="0.55" stroke="{{ $ink }}" stroke-opacity="0.4" stroke-width="2" />
            @break

        @default
            {{-- Blindplatte: glattes Blech. Ausser der Schliffstruktur nichts -
                 die Grundplatte oben traegt die Erkennbarkeit. --}}
            @for ($i = 0; $i < 5; $i++)
                <line x1="{{ $inset + 20 }}" y1="{{ $h * ($i + 1) / 6 }}" x2="{{ 1066 - $inset }}" y2="{{ $h * ($i + 1) / 6 }}"
                    stroke="{{ $ink }}" stroke-opacity="0.14" stroke-width="6" />
            @endfor
    @endswitch
@if ($scaled)</g>@endif
</svg>
@endif
