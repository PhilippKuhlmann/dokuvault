{{--
    Der Netzplan der Anmeldeseite. Zweimal eingebunden, in zwei Rollen:

    1. $hintergrund = true - blass, absolut positioniert, nur Linien und
       Kaesten. Unter lg liegt diese Fassung hinter der Karte und fuellt die
       ganze Flaeche; ab lg schrumpft sie auf einen Streifen links, damit
       hinter der Karte etwas zu sehen ist, ohne der lesbaren Fassung in der
       zweiten Spalte ins Gehege zu kommen.

    2. ohne $hintergrund - die lesbare Fassung. Erst ab lg, wo sie als eigene
       Rasterspalte neben der Maske steht.

    Beschriftungen traegt nur die lesbare Fassung. Im Hintergrund waeren sie an
    der Kartenkante mitten im Wort abgeschnitten, und ein halbes "RTR-CORE"
    sieht nach Fehler aus.

    Reine Zeichnung, kein Inhalt: aria-hidden, damit Vorleseprogramme nicht
    anfangen, IP-Bereiche vorzulesen, bevor jemand angemeldet ist. Die Werte
    sind feste Beispiele und stammen von keinem Kunden - die Seite sieht jeder,
    auch ohne Zugang.

    Farben ueber currentColor statt fill-/stroke-Utilities: so haengt die ganze
    Zeichnung an einer einzigen text-Klasse je Gruppe und folgt dem
    Dunkelmodus, ohne dass jede Linie ihre eigene dark:-Variante braucht.
--}}
@php
    $hintergrund ??= false;

    $lage = $hintergrund
        ? 'absolute inset-0 h-full w-full scale-[1.25] opacity-30
           lg:inset-y-0 lg:left-0 lg:right-auto lg:w-[30rem] lg:scale-[1.6] lg:opacity-25'
        : 'hidden h-auto max-h-[78vh] w-full lg:block';
@endphp

<svg class="pointer-events-none {{ $lage }}" viewBox="530 100 880 750"
    preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">

    {{-- Linien und Kaesten --}}
    <g class="text-chathams-blue-200 dark:text-cerulean-900" stroke="currentColor" stroke-width="1.25" fill="none">
        <rect x="690" y="146" width="150" height="52" />
        <path d="M765 198 V248" />
        <rect x="690" y="286" width="290" height="58" />

        {{-- Stammleitung: vom Core-Switch auf die drei VLANs --}}
        <path d="M835 344 V430" />
        <path d="M660 430 H1170" />
        <path d="M660 430 V520 M915 430 V520 M1170 430 V520" />

        <rect x="560" y="520" width="200" height="86" />
        <rect x="815" y="520" width="200" height="86" />
        <rect x="1070" y="520" width="200" height="86" />

        <path d="M1170 606 V708" />
        <rect x="1040" y="708" width="300" height="46" />

        {{-- Massstabsleiste, wie sie auf einer Zeichnung unten steht --}}
        <path d="M560 812 H740 M560 806 V818 M650 808 V816 M740 806 V818" />
    </g>

    {{-- Ports: gefuellte Kaestchen --}}
    <g class="text-chathams-blue-200 dark:text-cerulean-900" fill="currentColor" opacity="0.6">
        <rect x="702" y="300" width="12" height="9" /><rect x="718" y="300" width="12" height="9" />
        <rect x="734" y="300" width="12" height="9" /><rect x="750" y="300" width="12" height="9" />
        <rect x="766" y="300" width="12" height="9" /><rect x="782" y="300" width="12" height="9" />
        <rect x="798" y="300" width="12" height="9" /><rect x="814" y="300" width="12" height="9" />
        <rect x="702" y="313" width="12" height="9" /><rect x="718" y="313" width="12" height="9" />
        <rect x="734" y="313" width="12" height="9" /><rect x="750" y="313" width="12" height="9" />
        <rect x="766" y="313" width="12" height="9" /><rect x="782" y="313" width="12" height="9" />

        <rect x="1052" y="722" width="15" height="18" /><rect x="1071" y="722" width="15" height="18" />
        <rect x="1090" y="722" width="15" height="18" /><rect x="1109" y="722" width="15" height="18" />
        <rect x="1128" y="722" width="15" height="18" /><rect x="1147" y="722" width="15" height="18" />
        <rect x="1166" y="722" width="15" height="18" /><rect x="1185" y="722" width="15" height="18" />
        <rect x="1204" y="722" width="15" height="18" /><rect x="1223" y="722" width="15" height="18" />
        <rect x="1242" y="722" width="15" height="18" /><rect x="1261" y="722" width="15" height="18" />
    </g>

    @unless ($hintergrund)
        {{-- Beschriftungen --}}
        <g class="font-mono text-chathams-blue-400 dark:text-cerulean-800" fill="currentColor" font-size="13">
            <text x="700" y="132">RTR-CORE</text>
            <text x="700" y="272">SW-CORE</text>
            <text x="574" y="548">VLAN 10 · SERVER</text>
            <text x="829" y="548">VLAN 20 · CLIENTS</text>
            <text x="1084" y="548">VLAN 40 · WLAN</text>
            <text x="1050" y="694">PF-EG-01 · 24 PORT</text>
        </g>

        <g class="font-mono text-chathams-blue-300 dark:text-cerulean-900" fill="currentColor" font-size="11">
            <text x="702" y="177">10.0.0.1 · GW</text>
            <text x="1000" y="320">24 Port · PoE</text>

            <text x="574" y="570">10.0.10.0/24</text>
            <text x="574" y="590">.10 – .40 {{ __('statisch') }}</text>

            <text x="829" y="570">10.0.20.0/24</text>
            <text x="829" y="590">.100 – .200 DHCP</text>

            <text x="1084" y="570">10.0.40.0/24</text>
            <text x="1084" y="590">.100 – .250 DHCP</text>
        </g>

        {{-- Ein Paket laeuft die Stammleitung entlang. Nur in der lesbaren
             Fassung: zwei laufende Pakete auf einer Seite waeren Unruhe. --}}
        <path class="netzplan-paket text-cerulean-600 dark:text-cerulean-500" d="M835 344 V430 H1170 V520"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none" opacity="0.85" />
    @endunless
</svg>
