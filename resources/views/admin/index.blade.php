<x-admin-layout>
    @php
        $eventLabels = ['created' => 'Angelegt', 'updated' => 'Geändert', 'deleted' => 'Gelöscht', 'restored' => 'Wiederhergestellt'];
        $eventColors = ['created' => 'text-green-600 dark:text-green-400', 'updated' => 'text-cerulean-600 dark:text-cerulean-400', 'deleted' => 'text-red-600 dark:text-red-400', 'restored' => 'text-amber-600 dark:text-amber-400'];
        $typeColors = ['Lizenz' => 'bg-cerulean-50 text-cerulean-700 dark:bg-cerulean-900/30 dark:text-cerulean-300', 'Zertifikat' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300', 'Domain' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'];
        $chartMax = collect($chart)->max('count') ?: 1;
    @endphp

    <div class="p-3 sm:p-5 space-y-6">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Administration') }}</div>

        {{-- Verwaltungs-Kacheln --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach ($tiles as $tile)
                <a href="{{ $tile['route'] }}"
                    class="group flex items-center gap-3 p-4 bg-white rounded-xl border border-gray-200 shadow-sm transition hover:border-cerulean-300 hover:shadow-md dark:bg-gray-800 dark:border-gray-700 dark:hover:border-cerulean-500">
                    <span class="flex items-center justify-center w-11 h-11 rounded-lg bg-cerulean-50 text-cerulean-600 transition-colors group-hover:bg-cerulean-100 dark:bg-gray-700 dark:text-cerulean-400 shrink-0">
                        <x-dynamic-component :component="$tile['icon']" class="w-6 h-6" />
                    </span>
                    <span class="flex flex-col">
                        <span class="text-2xl font-bold leading-none text-chathams-blue-800 dark:text-gray-100">{{ $tile['count'] }}</span>
                        <span class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Inventar-Statistik --}}
        <div>
            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ __('Dokumentiertes Inventar (alle Kunden)') }}</div>
            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach ($inventory as $item)
                    <div class="flex items-center gap-3 p-3 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-50 text-cerulean-600 dark:bg-gray-700 dark:text-cerulean-400 shrink-0">
                            <x-dynamic-component :component="$item['icon']" class="w-5 h-5" />
                        </span>
                        <span class="flex flex-col">
                            <span class="text-xl font-bold leading-none text-chathams-blue-800 dark:text-gray-100">{{ $item['count'] }}</span>
                            <span class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item['label'] }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- Globale Ablauf-Übersicht --}}
            <div class="p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="text-lg font-CoconPro text-gray-900 dark:text-gray-100 mb-3">{{ __('Läuft demnächst ab') }}</div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($expiring as $e)
                        @php
                            $end = \Carbon\Carbon::parse($e['date'])->startOfDay();
                            $days = now()->startOfDay()->diffInDays($end, false);
                        @endphp
                        <a href="{{ $e['route'] ?? '#' }}" class="flex items-center justify-between gap-3 py-2.5 -mx-2 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide {{ $typeColors[$e['type']] ?? 'bg-gray-100 text-gray-600' }}">{{ $e['type'] }}</span>
                                    <span class="text-sm text-gray-900 dark:text-gray-100 truncate">{{ $e['name'] }}</span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $e['customer']?->name ?? '—' }}</div>
                            </div>
                            <div class="shrink-0 text-sm text-right">
                                @if ($days < 0)
                                    <span class="font-medium text-red-600 dark:text-red-400">abgelaufen</span>
                                @elseif ($days == 0)
                                    <span class="font-medium text-red-600 dark:text-red-400">heute</span>
                                @elseif ($days <= 14)
                                    <span class="font-medium text-amber-600 dark:text-amber-400">in {{ $days }} T.</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">in {{ $days }} T.</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Nichts läuft in den nächsten 60 Tagen ab 🎉') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Letzte Aktivitäten --}}
            <div class="p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-lg font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Letzte Aktivitäten') }}</div>
                    @can('admin_activity')
                        <a href="{{ route('admin.activity.index') }}" class="text-sm text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">alle →</a>
                    @endcan
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($activities as $a)
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <span class="text-sm font-medium {{ $eventColors[$a->description] ?? 'text-gray-700 dark:text-gray-300' }}">{{ $eventLabels[$a->description] ?? $a->description }}</span>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $a->subject_type ? class_basename($a->subject_type) : '' }}</span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $a->causer?->name ?? 'System' }}</div>
                            </div>
                            <div class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ $a->created_at->diffForHumans() }}</div>
                        </div>
                    @empty
                        <div class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Aktivitäten.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- Top-Kunden nach Geräten --}}
            <div class="p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="text-lg font-CoconPro text-gray-900 dark:text-gray-100 mb-3">{{ __('Top-Kunden nach Geräten') }}</div>
                @php $topMax = collect($topCustomers)->max('count') ?: 1; @endphp
                <div class="space-y-2.5">
                    @forelse ($topCustomers as $c)
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="text-gray-800 dark:text-gray-200 truncate">{{ $c['name'] }}</span>
                                <span class="text-gray-500 dark:text-gray-400 shrink-0 ml-2">{{ $c['count'] }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full rounded-full bg-cerulean-500" style="width: {{ $topMax ? max(3, round($c['count'] / $topMax * 100)) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Keine Daten.') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Aktivitäts-Verlauf 14 Tage --}}
            <div class="p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="text-lg font-CoconPro text-gray-900 dark:text-gray-100 mb-3">{{ __('Aktivität (14 Tage)') }}</div>
                <div class="flex items-end gap-1.5 h-28">
                    @foreach ($chart as $d)
                        <div class="flex-1 h-full flex items-end" title="{{ $d['label'] }} · {{ $d['count'] }} Aktivitäten">
                            <div class="w-full rounded-t bg-cerulean-500/80 hover:bg-cerulean-500 transition-colors min-h-[2px]"
                                style="height: {{ $chartMax ? round($d['count'] / $chartMax * 100) : 0 }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between mt-2 text-xs text-gray-400 dark:text-gray-500">
                    <span>{{ $chart[0]['label'] ?? '' }}</span>
                    <span>heute</span>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
