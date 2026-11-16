{{-- The guests below a host, grouped by kind. The first few of each group
     stand open, the rest fold away - a host with 60 guests would otherwise
     be a page of its own. Per guest see servermap._guest. --}}
@php
    $shown = 8;
@endphp
<div class="mx-auto w-72 overflow-hidden rounded-lg border border-gray-200 bg-white text-left dark:border-gray-700 dark:bg-gray-800">
    <div class="border-b border-gray-100 bg-gray-50 px-3 py-1.5 dark:border-gray-700 dark:bg-gray-700/40">
        <span class="font-DINPro-bold text-gray-900 dark:text-gray-100">{{ $title ?? __('Gäste') }}</span>
        <span class="text-xs text-gray-400">· {{ $list['count'] }}</span>
    </div>
    <div class="space-y-2 px-3 py-2 text-sm">
        @foreach ($list['groups'] as $group)
            <div>
                <div class="text-[10px] font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $group['label'] }} · {{ $group['count'] }}</div>
                @if ($list['visible'])
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @foreach (array_slice($group['guests'], 0, $shown) as $guest)
                            @include('servermap._guest')
                        @endforeach
                    </div>
                    @if ($group['count'] > $shown)
                        <details class="group/mehr">
                            <summary class="cursor-pointer list-none text-xs text-gray-500 hover:text-cerulean-600 dark:text-gray-400 dark:hover:text-cerulean-400 [&::-webkit-details-marker]:hidden">
                                <span class="group-open/mehr:hidden">{{ __('+ :count weitere', ['count' => $group['count'] - $shown]) }}</span>
                                <span class="hidden group-open/mehr:inline">{{ __('weniger') }}</span>
                            </summary>
                            <div class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-700/60 dark:border-gray-700/60">
                                @foreach (array_slice($group['guests'], $shown) as $guest)
                                    @include('servermap._guest')
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
</div>
