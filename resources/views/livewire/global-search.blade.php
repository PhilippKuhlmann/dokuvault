<div class="min-h-[calc(100vh-4rem)] flex flex-col items-center px-4 py-10 bg-gradient-to-br from-chathams-blue-50 via-cerulean-50 to-hawkes-blue-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">

    <div class="w-full sm:max-w-2xl">

        <div class="flex flex-col items-center mb-8">
            <div class="flex items-center justify-center w-16 h-16 mb-4 rounded-2xl bg-gradient-to-br from-cerulean-500 to-chathams-blue-800 shadow-lg">
                <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.4a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z" />
                </svg>
            </div>
            <span class="text-3xl text-chathams-blue-800 font-CoconPro dark:text-gray-100">
                {{ __('Globale Suche') }}
            </span>
            <span class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Name, IP, Seriennummer oder MAC über alle Geräte') }}
            </span>
        </div>

        <div class="w-full px-4 py-4 bg-white dark:bg-gray-800 shadow-xl rounded-2xl border border-gray-100 dark:border-gray-700">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.4a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z" />
                    </svg>
                </span>
                <input wire:model.live.debounce.300ms="search" type="search" name="search" placeholder="{{ __('z. B. 192.168.1.50, PC-07, Seriennummer …') }}"
                    class="block w-full pl-10 pr-4 py-2.5 rounded-lg border-gray-300 shadow-sm text-gray-900 placeholder-gray-400
                           focus:border-cerulean-500 focus:ring-cerulean-500
                           dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                    autofocus />
            </div>
        </div>

        @if (strlen((string) $search) >= 2)
            <div class="w-full px-4 py-4 mt-4 bg-white dark:bg-gray-800 shadow-xl rounded-2xl border border-gray-100 dark:border-gray-700">
                @forelse ($groups as $group)
                    <div class="mb-4 last:mb-0">
                        <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ $group['label'] }}
                        </div>
                        <div class="flex flex-col gap-1">
                            @foreach ($group['results'] as $result)
                                <a href="{{ route($group['slug'] . '.index', $result->customer) }}"
                                    class="flex items-center justify-between py-2 px-3 rounded-lg text-gray-800 dark:text-gray-100
                                           hover:bg-cerulean-50 dark:hover:bg-gray-700
                                           focus:outline-none focus:ring-2 focus:ring-cerulean-500 transition-colors">
                                    <span>
                                        {{-- Dieselbe Quelle wie Protokoll und Papierkorb: config('custom.name_fields'),
                                             ueberschreibbar je Model. Vorher stand hier eine eigene Kette
                                             (name, ssid, wan_ip) - ein Telefon hat keines davon und hiess
                                             deshalb "#14", ausgerechnet in der Suche nach seiner MAC. --}}
                                        {{ $result->protokollName() ?? '#'.$result->id }}
                                        <span class="text-sm text-gray-400">
                                            {{ $result->ip ?? $result->ip1 ?? '' }}
                                        </span>
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $result->customer?->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center text-gray-400 dark:text-gray-500 py-10">
                        <span>{{ __('Keine Treffer') }}</span>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</div>
