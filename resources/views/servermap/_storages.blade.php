{{-- The storages of a host or cluster: name, kind and size. Capacity
     only - how full it is belongs to the card's agent block, the map is
     documentation, not monitoring. --}}
@if ($storages)
    <div class="mt-1.5 border-t border-gray-200/70 pt-1 dark:border-gray-600/60">
        <div class="text-[10px] font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Speicher') }}</div>
        @foreach ($storages as $storage)
            <div class="flex items-baseline justify-between gap-2 text-xs">
                <span class="truncate text-gray-700 dark:text-gray-200" title="{{ $storage['name'] }}">{{ $storage['name'] }}
                    @if ($storage['type'])<span class="text-gray-400 dark:text-gray-500">{{ $storage['type'] }}</span>@endif
                </span>
                <span class="shrink-0 tabular-nums text-gray-600 dark:text-gray-300">{{ $storage['size'] }}</span>
            </div>
        @endforeach
    </div>
@endif
