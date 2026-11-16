{{-- One server: what it is built of. Fixed width like the boxes of the
     network map; long values are cut and stay readable in the tooltip. --}}
@php
    $tag = $host['url'] ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($tag === 'a') href="{{ $host['url'] }}" @endif
    class="mx-auto block w-72 rounded-lg border border-chathams-blue-200 bg-[#f3f6fb] px-3 py-2 text-left dark:border-gray-600 dark:bg-gray-700/40 {{ $tag === 'a' ? 'transition-colors hover:border-cerulean-400 dark:hover:border-cerulean-500' : '' }}">
    <div class="flex items-baseline justify-between gap-2">
        <span class="text-[10px] font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $host['type'] }}</span>
        @if ($host['address'])
            <span class="truncate font-mono text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ $host['address'] }}</span>
        @endif
    </div>
    <div class="truncate font-DINPro-bold text-gray-900 dark:text-gray-100" title="{{ $host['name'] }}">{{ $host['name'] }}</div>
    @foreach (array_filter([$host['model'], $host['os'], $host['cpu']]) as $zeile)
        <div class="truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $zeile }}">{{ $zeile }}</div>
    @endforeach
    @if ($host['facts'])
        <div class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">{{ implode(' · ', $host['facts']) }}</div>
    @endif
    @if ($host['assigned'])
        <div class="mt-1.5" title="{{ $host['assigned']['text'] }}">
            <div class="flex h-1 overflow-hidden rounded-sm bg-gray-200 dark:bg-gray-600">
                <div style="width: {{ (int) round($host['assigned']['pct']) }}%" @class(['bg-cerulean-500' => ! $host['assigned']['over'], 'bg-amber-500' => $host['assigned']['over']])></div>
            </div>
            <div @class(['mt-0.5 truncate text-[11px]', 'text-gray-500 dark:text-gray-400' => ! $host['assigned']['over'], 'text-amber-600 dark:text-amber-400' => $host['assigned']['over']])>{{ $host['assigned']['text'] }}</div>
        </div>
    @endif
</{{ $tag }}>
