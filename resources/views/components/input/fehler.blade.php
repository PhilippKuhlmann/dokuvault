{{--
    Die Fehlermeldung unter einem Feld - eine Stelle statt vier.

    Vorher standen im Projekt vier Schreibweisen nebeneinander: text-sm und
    text-xs, mit und ohne mt-1, und in 18 von 37 Fällen ohne Dunkelmodus-Farbe.
    text-red-600 auf dunklem Grund liest sich schlecht.

    Das Zeichen davor ist nicht Zierde: Es macht den Unterschied zwischen
    "Hinweis" und "Fehler" sichtbar, ohne dass die Farbe allein ihn tragen
    muss - wer Rot und Grau schlecht unterscheidet, sieht sonst nur Text.

    aria-live: Die Meldung erscheint nach dem Absenden, ohne dass die Seite neu
    lädt. Ohne den Hinweis bekommt ein Vorleseprogramm davon nichts mit.
--}}
@props(['feld'])

@error($feld)
    <p class="mt-1.5 flex items-start gap-1.5 text-xs text-red-600 dark:text-red-400" aria-live="polite">
        <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 012 0v5a1 1 0 01-2 0V6zm1 9a1.25 1.25 0 110-2.5 1.25 1.25 0 010 2.5z" clip-rule="evenodd" />
        </svg>
        <span>{{ $message }}</span>
    </p>
@enderror
