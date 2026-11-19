{{-- One guest: name and address, the operating system on its own line
     (with the EOL badge of the lists), then what it was given as grey
     chips and the services it carries in their catalogue colours - one
     long grey line was cut off and hard to read. --}}
<div @class(['py-1.5', 'opacity-60' => $guest['stopped']])>
    <div class="flex items-baseline justify-between gap-2">
        @if ($guest['url'])
            <a href="{{ $guest['url'] }}" class="truncate font-medium text-gray-900 hover:text-cerulean-600 dark:text-gray-100 dark:hover:text-cerulean-400" title="{{ $guest['name'] }}">{{ $guest['name'] }}</a>
        @else
            <span class="truncate font-medium text-gray-900 dark:text-gray-100" title="{{ $guest['name'] }}">{{ $guest['name'] }}</span>
        @endif
        <span class="shrink-0 font-mono text-xs text-gray-500 dark:text-gray-400 tabular-nums">
            @if ($guest['backup'] === false)<span class="mr-1 rounded bg-amber-100 px-1 font-sans text-[10px] text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="{{ __('In keinem Backup-Job von Cluster oder Host enthalten') }}">{{ __('kein Backup') }}</span>@endif@if ($guest['stopped'])<span class="mr-1 rounded bg-gray-100 px-1 font-sans text-[10px] text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ __('aus') }}</span>@endif{{ $guest['address'] }}
        </span>
    </div>
    @if ($guest['os'])
        <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-gray-600 dark:text-gray-300">
            <span>{{ $guest['os'] }}</span>
            <span class="shrink-0 whitespace-nowrap"><x-eol :os="$guest['osModel']" /></span>
        </div>
    @endif
    @if ($guest['specs'])
        <div class="mt-1 flex flex-wrap gap-1">
            @foreach ($guest['specs'] as $spec)
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] leading-none text-gray-700 tabular-nums dark:bg-gray-700 dark:text-gray-200">{{ $spec }}</span>
            @endforeach
        </div>
    @endif
    @include('servermap._services', ['services' => $guest['services']])
</div>
