{{--
    Beschaffung und Garantie in der Geraeteliste.

    Beim Garantiedatum steht der Rest der Laufzeit dabei, sobald es knapp wird -
    ein Datum allein laesst jeden selbst rechnen, und genau in dem Moment
    (Kunde am Telefon, Gerät defekt) will man nicht rechnen.
--}}
@props(['device'])

@php
    $tage = $device->garantieTage();

    $garantie = $device->warranty_until?->format('d.m.Y');

    if ($garantie && $tage !== null) {
        $garantie .= match (true) {
            $tage < 0 => ' — abgelaufen',
            $tage === 0 => ' — läuft heute ab',
            $tage <= 60 => ' — in '.$tage.' Tagen',
            default => '',
        };
    }
@endphp

<x-minitablecard :title="__('Beschaffung')" :array="[
    'Kaufdatum' => $device->purchase_date?->format('d.m.Y'),
    'Garantie bis' => $garantie,
    'Support-Ende' => $device->eol_date?->format('d.m.Y'),
    'Lieferant' => $device->supplier,
]" />
