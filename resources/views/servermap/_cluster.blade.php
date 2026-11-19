{{-- The cluster on top of its hosts: kind, what the members add up to,
     and the storage they share - Ceph, NFS, a backup server stand here
     once instead of on every node. Wider than the node of the network map:
     the storage list needs the room. --}}
@php
    $tag = $cluster['url'] ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($tag === 'a') href="{{ $cluster['url'] }}" @endif
    class="mx-auto block w-72 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-center dark:border-sky-800 dark:bg-sky-900/20 {{ $tag === 'a' ? 'transition-colors hover:border-cerulean-400 dark:hover:border-cerulean-500' : '' }}">
    <div class="text-[10px] font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $cluster['type'] }}</div>
    <div class="truncate font-DINPro-bold text-gray-900 dark:text-gray-100" title="{{ $cluster['name'] }}">{{ $cluster['name'] }}</div>
    @if ($cluster['facts'])
        <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ implode(' · ', $cluster['facts']) }}</div>
    @endif
    @if ($cluster['storages'])
        <div class="text-left">
            @include('servermap._storages', ['storages' => $cluster['storages']])
        </div>
    @endif
</{{ $tag }}>
