@props(['adresse' => null])

{{-- Was am Geraet steht: die Adresse, oder bei DHCP nur "DHCP".

     Eine geliehene Adresse stimmt nur bis zum naechsten Neustart. Sie als
     Wert anzubieten - mit Kopierknopf daneben - laedt dazu ein, sich darauf
     zu verlassen. Deshalb ohne Knopf: "DHCP" kopiert niemand. --}}
@if ($adresse?->istDhcp())
    <span class="text-gray-500 dark:text-gray-400">{{ __($adresse->anzeige()) }}</span>
@elseif ($adresse)
    <x-copy :value="$adresse->address" />
@endif
