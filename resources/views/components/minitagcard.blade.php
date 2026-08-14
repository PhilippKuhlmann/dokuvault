@props(['title', 'array' => []])

{{-- Wie x-minitablecard: leere Einträge raus, Block ganz weg, wenn nichts
     übrig bleibt. Die Dienste kommen aus explode(',', ...) und liefern bei
     einem leeren Feld [''] - das ergab bisher eine leere Sprechblase. --}}
@php ($gefuellt = array_filter((array) $array, fn ($v) => filled($v)))

@if (count($gefuellt))
    <div class="w-full mb-5 break-inside-avoid">
        <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ $title }}
        </div>
        {{-- Farbe kommt aus dem Dienste-Katalog der Administration; was dort
             nicht steht, bleibt neutral. --}}
        @php ($farben = \App\Models\Service::farbzuordnung())

        <div class="flex flex-wrap gap-2">
            @foreach ($gefuellt as $value)
                <x-servicechip :name="$value" :farbe="$farben[mb_strtolower(trim($value))] ?? null" />
            @endforeach
        </div>
    </div>
@endif
