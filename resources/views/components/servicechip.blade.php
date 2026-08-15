@props(['name', 'farbe' => null, 'beschreibung' => null])

@php
    // Freie Hex-Farbe statt fester Palette; die Schriftfarbe rechnet das Model
    // aus der Helligkeit, damit Kachel und Auswahl im Formular dieselbe Regel
    // benutzen (App\Models\Service::kachelStil).
    $stil = \App\Models\Service::kachelStil($farbe);

    // Ohne ausdrueckliche Beschreibung die aus dem Katalog nehmen: Die Listen
    // reichen nur den Namen durch.
    $titel = $beschreibung ?: (\App\Models\Service::beschreibungen()[mb_strtolower(trim($name))] ?? null);
@endphp

{{-- Die Beschreibung als eigenes Hover-Fenster statt als title: Der Browser-
     Tooltip kommt erst nach einer Sekunde und laesst sich nicht gestalten. --}}
<x-hovertext :text="$titel">
    @if ($stil)
        <span {{ $attributes->merge(['class' => 'inline-block px-3 py-1 text-sm rounded']) }} style="{{ $stil }}">{{ $name }}</span>
    @else
        {{-- Ohne Katalogeintrag bleibt die Kachel neutral. --}}
        <span {{ $attributes->merge(['class' => 'inline-block px-3 py-1 text-sm rounded bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-100']) }}>{{ $name }}</span>
    @endif
</x-hovertext>
