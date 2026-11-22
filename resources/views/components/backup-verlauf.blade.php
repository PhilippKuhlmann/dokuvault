{{--
    The run grid of a backup: one square per day, oldest left, today right
    (App\Support\BackupRunDays). Green ok, amber warning, red failed - the
    worst run of the day counts. Grey: no run that day - a job that stopped
    running shows as a gap, it stayed green while the squares counted runs.
    Dashed and empty: before the oldest run kept, nothing is known.

    Date and result at once on hover or tap (x-hovertext). Used on the admin
    overview and the customer's backup card, so both look the same.

    $backup: with recentRuns and firstRun loaded.
    cells: already built (the admin overview counts them as well).
    gross: the larger squares of the admin overview.
--}}
@props(['backup', 'cells' => null, 'gross' => false])

@php
    $cells ??= \App\Support\BackupRunDays::for($backup, \App\Models\Setting::backupDays());
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1']) }}>
    @if ($backup->firstRun === null)
        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('noch keine Läufe gemeldet') }}</span>
    @else
        @foreach ($cells as $cell)
            {{-- tabindex so a tap on a touch screen shows the tooltip too. --}}
            <x-hovertext dunkel class="leading-none" :text="$cell['label']">
                <span tabindex="0" @class([
                        'inline-block rounded-sm cursor-default focus:outline-hidden focus:ring-2 focus:ring-cerulean-500',
                        'h-5 w-3.5' => $gross,
                        'h-4 w-2.5' => ! $gross,
                        'bg-green-600' => $cell['state'] === 'ok',
                        'bg-amber-400' => $cell['state'] === 'warning',
                        'bg-red-600' => $cell['state'] === 'failed',
                        'bg-gray-200 dark:bg-gray-600' => $cell['state'] === 'none',
                        'border border-dashed border-gray-300 dark:border-gray-600' => $cell['state'] === 'unknown',
                    ])
                    aria-label="{{ $cell['label'] }}"></span>
            </x-hovertext>
        @endforeach
    @endif
</div>
