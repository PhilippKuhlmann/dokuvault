<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Checklisten') }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('On- und Offboarding von Mitarbeitern.') }}</div>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('checklist_manage')
                    <a href="{{ route('checklist.settings', $customer) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-DINPro-bold text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">{{ __('Einstellungen') }}</a>
                    <a href="{{ route('checklist.offboarding.create', $customer) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-DINPro-bold text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">{{ __('Offboarding starten') }}</a>
                @endcan
                @canany(['checklist_request', 'checklist_manage'])
                    <x-input.linkbutton color="knopf" :link="route('checklist.onboarding.create', $customer)" :label="__('+ Neuer Mitarbeiter')" />
                @endcanany
            </div>
        </div>

        <x-panel polster="keins">
            <div class="border-b border-gray-100 px-5 py-3 text-lg font-CoconPro text-chathams-blue-800 dark:border-gray-700 dark:text-gray-100">{{ __('Offen') }}</div>
            @forelse ($open as $checklist)
                <a href="{{ route('checklist.show', [$customer, $checklist]) }}"
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 last:border-b-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/40">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-gray-900 dark:text-gray-100">
                            @include('checklist._type', ['type' => $checklist->type])
                            <span class="truncate font-DINPro-medium">{{ $checklist->fullName() }}</span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $checklist->isOnboarding() ? __('Arbeitsbeginn') : __('Letzter Tag') }} {{ $checklist->reference_date->format('d.m.Y') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        @if ($checklist->approval_count)
                            <span class="text-amber-600 dark:text-yellow-400">{{ trans_choice(':count Freigabe offen|:count Freigaben offen', $checklist->approval_count) }}</span>
                        @endif
                        <span class="tabular-nums text-gray-600 dark:text-gray-300">{{ $checklist->done_count }}/{{ $checklist->items_count }}</span>
                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-cerulean-600" style="width: {{ $checklist->items_count ? round($checklist->done_count / $checklist->items_count * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Keine offenen Checklisten.') }}</div>
            @endforelse
        </x-panel>

        @if ($completed->isNotEmpty())
            <x-panel polster="keins">
                <div class="border-b border-gray-100 px-5 py-3 text-lg font-CoconPro text-chathams-blue-800 dark:border-gray-700 dark:text-gray-100">{{ __('Abgeschlossen') }}</div>
                @foreach ($completed as $checklist)
                    <a href="{{ route('checklist.show', [$customer, $checklist]) }}"
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-2.5 last:border-b-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/40">
                        <div class="flex min-w-0 items-center gap-2 text-gray-700 dark:text-gray-300">
                            @include('checklist._type', ['type' => $checklist->type])
                            <span class="truncate">{{ $checklist->fullName() }}</span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('abgeschlossen am') }} {{ $checklist->completed_at->format('d.m.Y') }}</span>
                    </a>
                @endforeach
            </x-panel>
        @endif
    </div>
</x-app-layout>
