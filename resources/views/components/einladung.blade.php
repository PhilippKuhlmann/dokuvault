@use('App\Support\Zeit')

@props(['user'])

{{--
    Die Einladung in einer Liste: Zeichen und Datum nebeneinander.

    Anders als bei der zweiten Stufe reicht das Zeichen hier nicht allein - das
    Datum ist die eigentliche Auskunft: "offen seit gestern" heisst warten,
    "offen seit drei Wochen" heisst nachfassen. Das Zeichen ersetzt nur das
    Wort davor, nicht die Zeile.

    Vier Zustaende, drei davon mit eigener Form und Farbe:

      Umschlag, bernstein  unterwegs, wartet auf den Eingeladenen
      Warndreieck, rose    die Frist ist um, der Link traegt nicht mehr
      Haken, gruen         Kennwort gesetzt, Einladung abgeschlossen
      Strich, grau         nie eingeladen - der Zugang kam auf anderem Weg

    Die Reihenfolge der Abfrage ist nicht beliebig: Wer erneut eingeladen wird,
    nachdem er schon einmal eingeloest hat, traegt beide Zeitpunkte. Dann gilt
    die neue, offene Einladung - der alte Haken waere dort die falsche Antwort.

    Verschiedene Formen und nicht nur verschiedene Farben: Sonst haengt die
    Unterscheidung an etwas, das nicht jeder sieht.
--}}

@if ($user->einladungAbgelaufen())
    <span class="inline-flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
            stroke="currentColor" class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400"
            role="img" aria-label="{{ __('abgelaufen') }}">
            <title>{{ __('abgelaufen') }}</title>
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        <span>{{ Zeit::anzeigen($user->invited_at, 'd.m.Y') }}</span>
    </span>
@elseif ($user->einladungOffen())
    <span class="inline-flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
            stroke="currentColor" class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
            role="img" aria-label="{{ __('offen seit') }}">
            <title>{{ __('offen seit') }}</title>
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
        <span>{{ Zeit::anzeigen($user->invited_at, 'd.m.Y') }}</span>
    </span>
@elseif ($user->einladungEingeloest())
    <span class="inline-flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2"
            stroke="currentColor" class="h-5 w-5 shrink-0 text-green-600 dark:text-green-400"
            role="img" aria-label="{{ __('eingelöst') }}">
            <title>{{ __('eingelöst') }}</title>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
        <span>{{ Zeit::anzeigen($user->invitation_accepted_at, 'd.m.Y') }}</span>
    </span>
@else
    <span class="text-gray-400 dark:text-gray-500" title="{{ __('nie eingeladen') }}">—</span>
@endif
