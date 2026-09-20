{{--
    Die Fehlermeldung zu einem Feld - eine Stelle statt vier.

    Vorher standen im Projekt vier Schreibweisen nebeneinander: text-sm und
    text-xs, mit und ohne mt-1, und in 18 von 37 Fällen ohne Dunkelmodus-Farbe.
    text-red-600 auf dunklem Grund liest sich schlecht.

    Das Zeichen davor ist nicht Zierde: Es macht den Unterschied zwischen
    "Hinweis" und "Fehler" sichtbar, ohne dass die Farbe allein ihn tragen
    muss - wer Rot und Grau schlecht unterscheidet, sieht sonst nur Text.

    aria-live: Die Meldung erscheint nach dem Absenden, ohne dass die Seite neu
    lädt. Ohne den Hinweis bekommt ein Vorleseprogramm davon nichts mit.

    Zwei Wege zur Meldung, weil es zwei Arten von Formularen gibt:

      feld="name"                    - der Normalfall, aus dem Standardbeutel
      :messages="$errors->x->get()"  - für Formulare mit eigenem Fehlerbeutel

    Der zweite Weg kommt von x-input.error, das diese Komponente ersetzt hat.
    Profil und zweite Stufe teilen sich eine Seite und brauchen deshalb je
    einen eigenen Beutel; @error liest nur den Standardbeutel und fände dort
    nichts.

    art="banner" ist die Fassung über einem Formular statt unter einem Feld:
    dieselbe Kiste wie x-input.fehlerliste. Auf der Anmeldeseite hängt die
    Meldung an keinem einzelnen Feld ("Diese Zugangsdaten passen nicht") und
    darf nicht als kleine Zeile im Nichts stehen.
--}}
@props(['feld' => null, 'messages' => null, 'art' => 'feld'])

@php
    $meldungen = $messages !== null
        ? array_filter((array) $messages)
        : ($feld ? $errors->get($feld) : []);

    // Abstände der Aufrufstelle schlagen den eigenen. Ohne das stünden mt-1.5
    // und ein mt-2 von aussen im selben Klassenattribut, und welches gewinnt,
    // entschiede die Reihenfolge im gebauten CSS - nicht die im Blade.
    // Dieselbe Aufteilung wie in x-input.select.
    $eigeneAbstaende = (bool) preg_match('/(^|\s)m[tby]?-/', $attributes->get('class') ?? '');

    $banner = $art === 'banner';
@endphp

@if ($meldungen)
    <p role="{{ $banner ? 'alert' : 'status' }}" aria-live="polite"
        {{ $attributes->merge(['class' => trim(collect([
            'flex items-start gap-1.5',
            $banner
                ? 'gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300'
                : 'text-xs text-red-600 dark:text-red-400',
            $eigeneAbstaende ? '' : ($banner ? 'mb-4' : 'mt-1.5'),
        ])->filter()->implode(' '))]) }}>

        <svg class="{{ $banner ? 'mt-0.5 h-4 w-4' : 'mt-0.5 h-3.5 w-3.5' }} shrink-0"
            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 012 0v5a1 1 0 01-2 0V6zm1 9a1.25 1.25 0 110-2.5 1.25 1.25 0 010 2.5z" clip-rule="evenodd" />
        </svg>

        {{-- Mehrere Meldungen je Feld gibt es, sobald eine Regel nicht mit
             bail abbricht. x-input.error zählte sie als Liste auf; hier
             untereinander, damit eine einzelne Meldung nicht wie ein
             Listenpunkt mit nur einem Eintrag aussieht. --}}
        <span class="flex flex-col gap-0.5">
            @foreach ($meldungen as $meldung)
                <span>{{ $meldung }}</span>
            @endforeach
        </span>
    </p>
@endif
