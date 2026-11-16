<div @class(['py-0.5', 'opacity-60' => $guest['stopped']])>
    <div class="flex items-baseline justify-between gap-2">
        @if ($guest['url'])
            <a href="{{ $guest['url'] }}" class="truncate text-gray-800 hover:text-cerulean-600 dark:text-gray-200 dark:hover:text-cerulean-400" title="{{ $guest['name'] }}">{{ $guest['name'] }}</a>
        @else
            <span class="truncate text-gray-800 dark:text-gray-200" title="{{ $guest['name'] }}">{{ $guest['name'] }}</span>
        @endif
        <span class="shrink-0 font-mono text-xs text-gray-400 tabular-nums">
            @if ($guest['stopped'])<span class="mr-1 rounded bg-gray-100 px-1 font-sans text-[10px] text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ __('aus') }}</span>@endif{{ $guest['address'] }}
        </span>
    </div>
    @if ($guest['facts'])
        <div class="truncate text-xs text-gray-500 dark:text-gray-400" title="{{ implode(' · ', $guest['facts']) }}">{{ implode(' · ', $guest['facts']) }}</div>
    @endif
</div>
