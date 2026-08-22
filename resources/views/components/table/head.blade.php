@props(['labels'])

{{-- Uebersetzt hier statt an jeder der 21 Aufrufstellen: Die meisten reichen
     rohe deutsche Strings durch ("Bezeichnung", "Höheneinheiten" ...), nur
     eine Handvoll wickelt sie schon in __() ein. Im Englischen stand deshalb
     die deutsche Spaltenueberschrift da - "BEZEICHNUNG" blieb "BEZEICHNUNG".
     __() auf einen bereits uebersetzten String angewandt (die Handvoll
     Ausnahmen) liefert denselben String zurueck, ist also unschaedlich. --}}
<thead class="text-xs uppercase tracking-wide text-gray-500 bg-gray-50 border-b border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
    <tr>
        @foreach ($labels as $label)
            <th scope="col" class="py-2.5 px-4 font-semibold">
                {{ is_string($label) ? __($label) : $label }}
            </th>
        @endforeach
    </tr>
</thead>
