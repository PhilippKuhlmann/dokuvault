@props(['device', 'title' => null])

@php
    // Adressen stehen ausschliesslich in der Relation ipAddresses - eine
    // IP-Spalte am Geraet gibt es nicht mehr.
    $zeilen = [];


    $weitereAdressen = $device->relationLoaded('ipAddresses')
        ? $device->ipAddresses
        : $device->ipAddresses()->with('network')->get();

    foreach ($weitereAdressen as $weitere) {
        $netz = $weitere->network;
        $rolle = $weitere->label ?: __('Weitere');

        $zeilen[] = [
            'rolle' => $rolle,
            'adresse' => $weitere->address,
            // Netzname und VLAN-Nummer zusammen ("DMZ · VLAN 20"): Der Name sagt
            // wofuer, die Nummer braucht man am Switch. Heisst die Bezeichnung
            // schon wie das Netz ("Clients"), bleibt nur die Nummer stehen.
            'zusatz' => $netz?->anzeige($netz->description === $rolle),
        ];
    }
@endphp

@if (count($zeilen))
    <div class="w-full mb-5 break-inside-avoid">
        <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ $title ?? __('IP-Adressen') }}
        </div>
        <div class="text-sm dark:text-gray-100">
            <table class="w-full">
                @foreach ($zeilen as $zeile)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                        {{-- Kein whitespace-nowrap: Der Kartenkoerper laeuft in CSS-Spalten, und
                             eine Tabelle schrumpft nicht unter ihre Mindestbreite - mit unbrechbarer
                             Beschriftung lief sie in die Nachbarspalte und aus der Karte heraus
                             ("10.10.30.7Hersteller"). Umgebrochen wird nur, wenn es sonst nicht passt. --}}
                        <td class="py-1 pr-6 align-top text-gray-500 dark:text-gray-400">{{ $zeile['rolle'] }}</td>
                        <td class="py-1 break-words align-top text-gray-900 dark:text-gray-100">
                            <x-copy :value="$zeile['adresse']" />
                            @if ($zeile['zusatz'])
                                <div class="text-xs text-gray-400 dark:text-gray-500">{{ $zeile['zusatz'] }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endif
