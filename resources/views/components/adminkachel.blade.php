{{--
    Die Kennzahl-Kachel ueber den Admin-Listen: Beschriftung, darunter der Wert.

    Feste Groesse, damit sie auf jeder Admin-Seite gleich aussieht. Vorher stand
    derselbe Block dreizehnmal kopiert in neun Dateien - und genau das lief
    auseinander: Auf der Benutzer- und der Rollenliste steht statt einer Zahl
    ein Name, und "Anke Brinkmann (Kunde)" brach auf zwei Zeilen und lief aus
    seinem 40 px hohen Kasten heraus bis ueber die Kartenkante.

    art="name" ist die Fassung dafuer: eine Stufe kleiner, mit engem
    Zeilenabstand, sodass zwei Zeilen in dieselbe feste Hoehe passen wie eine
    grosse Zahl. Laenger als zwei Zeilen wird gekuerzt statt die Kachel zu
    dehnen - der ganze Name steht dann im title.

    ton faerbt die Beschriftung: Die EOL-Uebersicht warnt damit, "ohne Support"
    ist etwas anderes als "Kunden Gesamt".
--}}
@props(['label', 'art' => 'zahl', 'ton' => 'ruhig'])

@php
    $toene = [
        'ruhig' => 'text-cerulean-500',
        'warnung' => 'text-amber-600 dark:text-amber-400',
        'fehler' => 'text-rose-600 dark:text-rose-400',
    ];

    $arten = [
        'zahl' => 'text-4xl',
        'name' => 'text-xl leading-tight wrap-break-word line-clamp-2',
    ];

    $wert = trim((string) $slot);
@endphp

<x-panel polster="eng" class="w-64">
    <div class="h-8 text-center font-CoconPro {{ $toene[$ton] ?? $toene['ruhig'] }}">
        {{ $label }}
    </div>

    {{-- Die feste Hoehe sitzt am Kasten, nicht am Text: Eine grosse Zahl
         braucht eine Zeile, ein Name bis zu zwei - beide sollen mittig in
         derselben Hoehe stehen. --}}
    <div class="flex h-14 items-center justify-center text-center font-CoconPro
                text-chathams-blue-800 dark:text-gray-100">
        <span class="{{ $arten[$art] ?? $arten['zahl'] }}"
            @if ($art === 'name') title="{{ $wert }}" @endif>{{ $wert }}</span>
    </div>
</x-panel>
