@props(['name', 'farbe' => null])

@php
    // Freie Hex-Farbe statt fester Palette. Die Schriftfarbe wird aus der
    // Helligkeit des Hintergrunds abgeleitet, sonst waere sie auf hellen oder
    // dunklen Kacheln nicht lesbar - und zwar in beiden Themes gleich, weil der
    // Hintergrund fest ist.
    $hex = is_string($farbe) && preg_match('/^#[0-9a-fA-F]{6}$/', $farbe) ? $farbe : null;

    if ($hex) {
        [$r, $g, $b] = array_map(fn ($teil) => hexdec($teil) / 255, str_split(substr($hex, 1), 2));
        // Wahrgenommene Helligkeit (ITU-R BT.601) - reicht fuer die Entscheidung
        // schwarz oder weiss und kommt ohne Bibliothek aus.
        $helligkeit = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        $stil = 'background-color: '.$hex.'; color: '.($helligkeit > 0.6 ? '#111827' : '#ffffff').';';
    }
@endphp

@if ($hex)
    <span {{ $attributes->merge(['class' => 'px-3 py-1 text-sm rounded']) }} style="{{ $stil }}">{{ $name }}</span>
@else
    {{-- Ohne Katalogeintrag bleibt die Kachel neutral. --}}
    <span {{ $attributes->merge(['class' => 'px-3 py-1 text-sm rounded bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-100']) }}>{{ $name }}</span>
@endif
