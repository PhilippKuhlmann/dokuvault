{{--
    Bar chart for the statistics pages: one bar per entry, growing from the
    bottom, instant tooltip (x-hovertext dark). Labels below every few
    bars when there are many.

    $werte: list of ['beschriftung' => '06', 'titel' => '06.10. 08:00', 'wert' => 12, 'text' => optional tooltip]
    $farbe: classes of the bar, e.g. 'bg-cerulean-500 dark:bg-cerulean-600'
--}}
@props(['werte' => [], 'farbe' => 'bg-cerulean-500 dark:bg-cerulean-600', 'hoehe' => 'h-40', 'einheit' => ''])

@php
    $max = max(1, collect($werte)->max('wert'));
    $jede = count($werte) > 16 ? (int) ceil(count($werte) / 10) : 1;
@endphp

<div {{ $attributes }}>
    <div class="flex {{ $hoehe }} items-end gap-0.5">
        @foreach ($werte as $b)
            <div class="flex h-full min-w-0 flex-1 items-end">
                <x-hovertext dunkel class="block! w-full"
                    style="height: {{ $b['wert'] ? max(2, round($b['wert'] / $max * 100)) : 0 }}%"
                    :text="$b['text'] ?? ($b['titel'].' · '.number_format($b['wert'], 0, ',', '.').($einheit ? ' '.$einheit : ''))">
                    <span tabindex="0" class="block h-full w-full rounded-t {{ $farbe }} focus:outline-hidden focus:ring-2 focus:ring-gray-300"></span>
                </x-hovertext>
            </div>
        @endforeach
    </div>
    <div class="mt-1 flex gap-0.5 text-[10px] text-gray-400 dark:text-gray-500">
        @foreach ($werte as $i => $b)
            <span class="min-w-0 flex-1 truncate text-center">{{ $i % $jede === 0 ? $b['beschriftung'] : '' }}</span>
        @endforeach
    </div>
</div>
