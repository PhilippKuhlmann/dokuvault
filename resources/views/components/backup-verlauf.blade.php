{{--
    The last runs of a backup as small squares: green ok, amber warning,
    red failed. Oldest left, newest right - read like a time line. Date and
    result at once on hover or tap (x-hovertext). Used on the admin overview and the customer's backup
    card, so both look the same.

    $runs: newest first, as Backup::recentRuns() delivers them.
--}}
@props(['runs'])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1']) }}>
    @forelse ($runs->reverse() as $lauf)
        {{-- Instant tooltip (x-hovertext, dark like the menu icons); tabindex
             so a tap on a touch screen shows it too. --}}
        <x-hovertext dunkel class="leading-none" :text="$lauf->finished_at->format('d.m.Y H:i').' · '.__(\App\Models\Backup::STATUS[$lauf->status] ?? $lauf->status)">
            <span tabindex="0" @class([
                    'inline-block h-4 w-3 rounded-sm cursor-default focus:outline-hidden focus:ring-2 focus:ring-cerulean-500',
                    'bg-green-500' => $lauf->status === 'ok',
                    'bg-amber-400' => $lauf->status === 'warning',
                    'bg-red-500' => $lauf->status === 'failed',
                ])
                aria-label="{{ $lauf->finished_at->format('d.m.Y H:i') }} · {{ __(\App\Models\Backup::STATUS[$lauf->status] ?? $lauf->status) }}"></span>
        </x-hovertext>
    @empty
        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('noch keine Läufe gemeldet') }}</span>
    @endforelse
</div>
