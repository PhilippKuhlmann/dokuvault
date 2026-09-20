{{--
    Die Flaeche, auf der ein Abschnitt steht: weisser Grund, feiner Rand,
    kaum Schatten.

    Sie stand zweiundfuenfzigmal von Hand im Projekt, und die Masse liefen
    auseinander - p-3, p-4, p-5, p-8 und p-10 nebeneinander, ohne dass ein
    Unterschied dahinterstand. Hier sind daraus vier Abstufungen geworden,
    und jede hat einen Grund:

      keins   Die Flaeche traegt eine Tabelle. Die bringt ihren Abstand in den
              Zellen mit; ein Polster am Rahmen ergaebe einen doppelten Rand.
      eng     Eine Leiste ueber einer Liste - Suchfeld, Filter, Zaehler.
      normal  Der Regelfall: ein Abschnitt mit Ueberschrift und Inhalt.
      weit    Die Seite ist leer und sagt das. Der Satz steht mittig in viel
              Luft, sonst liest er sich wie ein vergessener Rest.

    Nicht zu verwechseln mit x-card: Das ist die Geraetekarte, deren Inhalt in
    Spalten laeuft. x-panel ist nur der Rahmen - und x-card baut darauf auf.
--}}
@props(['polster' => 'normal'])

@php
    $polsterungen = [
        'keins'  => '',
        'eng'    => 'p-4',
        'normal' => 'p-5',
        'weit'   => 'p-10',
    ];
@endphp

<div {{ $attributes->merge(['class' => trim(
    'rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-700 dark:bg-gray-800 '
    .($polsterungen[$polster] ?? $polsterungen['normal'])
)]) }}>
    {{ $slot }}
</div>
