{{-- One box in the map: internet connection or gateway device. Fixed width,
     long names are cut and stay readable in the tooltip. --}}
@php($tag = ($node['url'] ?? null) ? 'a' : 'div')
<{{ $tag }} @if ($tag === 'a') href="{{ $node['url'] }}" @endif title="{{ $node['name'] }}"
    class="mx-auto block w-56 rounded-lg border px-3 py-2 text-center {{ $class ?? '' }} {{ $tag === 'a' ? 'transition-colors hover:border-cerulean-400 dark:hover:border-cerulean-500' : '' }}">
    @isset($node['type'])
        <div class="text-[10px] font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __($node['type']) }}</div>
    @endisset
    <div class="truncate font-DINPro-bold text-gray-900 dark:text-gray-100">{{ $node['name'] }}</div>
    @if ($node['detail'] ?? null)
        <div class="truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $node['detail'] }}">{{ $node['detail'] }}</div>
    @endif
</{{ $tag }}>
