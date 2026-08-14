@props(['device', 'title' => null])

@php
    // Die zusaetzlichen Adressen (Relation ipAddresses) standen bisher nur im
    // Bearbeiten-Formular. Wer die Doku liest, sah sie nie - obwohl genau dort
    // steht, ueber welches VLAN ein Geraet noch erreichbar ist.
    $zeilen = [];

    foreach ([__('Primär') => $device->ip1 ?? $device->ip ?? null, __('Sekundär') => $device->ip2 ?? null] as $rolle => $adresse) {
        if (filled($adresse)) {
            $zeilen[] = ['rolle' => $rolle, 'adresse' => $adresse, 'zusatz' => null];
        }
    }

    $weitereAdressen = $device->relationLoaded('ipAddresses')
        ? $device->ipAddresses
        : $device->ipAddresses()->with('network')->get();

    foreach ($weitereAdressen as $weitere) {
        $netz = $weitere->network;
        $rolle = $weitere->label ?: __('Weitere');
        $netzName = $netz ? ($netz->description ?: 'VLAN '.$netz->vlanId) : null;

        $zeilen[] = [
            'rolle' => $rolle,
            'adresse' => $weitere->address,
            // Heisst die Bezeichnung wie das VLAN ("Clients"), stuende sie zweimal da.
            'zusatz' => $netzName === $rolle ? null : $netzName,
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
                        <td class="py-1 pr-6 align-top whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $zeile['rolle'] }}</td>
                        <td class="py-1 w-full align-top text-gray-900 dark:text-gray-100">
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
