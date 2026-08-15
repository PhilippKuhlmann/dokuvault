<x-admin-layout>

    <div class="flex w-full flex-wrap pl-3 pt-3 gap-3">
        <div class="w-64 rounded-xl border border-gray-200 bg-white shadow-sm p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="h-8 text-center font-CoconPro text-rose-600 dark:text-rose-400">
                {{ __('Ohne Support') }}
            </div>
            <div class="h-10 text-chathams-blue-800 dark:text-gray-100 text-center font-CoconPro text-4xl">
                {{ $anzahlAbgelaufen }}
            </div>
        </div>

        <div class="w-64 rounded-xl border border-gray-200 bg-white shadow-sm p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="h-8 text-center font-CoconPro text-amber-600 dark:text-amber-400">
                {{ __('Läuft bald aus') }}
            </div>
            <div class="h-10 text-chathams-blue-800 dark:text-gray-100 text-center font-CoconPro text-4xl">
                {{ $anzahlBald }}
            </div>
        </div>
    </div>

    {{-- Nur Uebersicht: Der Neu-Knopf haette hier nichts anzulegen. --}}
    <x-sitetopmenu can="nichts-anzulegen" />

    @forelse ($nachKunde as $kunde => $geraete)
        @php ($abgelaufen = $geraete->filter(fn ($g) => $g['os']->istEol())->count())

        <x-card plain>
            <x-slot:head>
                <div class="flex w-full flex-wrap items-center gap-x-6 gap-y-2 p-3">
                    <div class="text-2xl dark:text-gray-100">{{ $kunde }}</div>

                    <div class="ml-auto flex items-center gap-3 text-sm">
                        @if ($abgelaufen)
                            <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-rose-800 dark:bg-rose-900 dark:text-rose-100">
                                {{ $abgelaufen }} {{ __('ohne Support') }}
                            </span>
                        @endif
                        <span class="text-gray-500 dark:text-gray-400">{{ $geraete->count() }} {{ __('Geräte') }}</span>
                    </div>
                </div>
            </x-slot>

            <x-slot:body>
                {{-- Eigener Scrollbereich: vier Spalten passen auf schmalen
                     Bildschirmen nicht nebeneinander. --}}
                <div class="w-full overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-sm">
                        <thead class="text-xs uppercase tracking-wide text-gray-400 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="py-2 pr-4 text-left font-semibold">{{ __('Gerät') }}</th>
                                <th class="py-2 pr-4 text-left font-semibold">{{ __('Typ') }}</th>
                                <th class="py-2 pr-4 text-left font-semibold">{{ __('Betriebssystem') }}</th>
                                <th class="py-2 pr-4 text-left font-semibold">{{ __('Support-Ende') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($geraete as $geraet)
                                <tr class="border-b border-gray-50 last:border-0 dark:border-gray-700/50">
                                    <td class="py-1.5 pr-4 text-gray-900 dark:text-gray-100">
                                        @if ($geraet['route'])
                                            <a href="{{ $geraet['route'] }}" class="text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">{{ $geraet['name'] }}</a>
                                        @else
                                            {{ $geraet['name'] }}
                                        @endif
                                    </td>
                                    <td class="py-1.5 pr-4 text-gray-500 dark:text-gray-400">{{ __($geraet['typ']) }}</td>
                                    <td class="py-1.5 pr-4 text-gray-600 dark:text-gray-300">{{ $geraet['os']->name }}</td>
                                    <td class="py-1.5 pr-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-gray-900 dark:text-gray-100">{{ $geraet['os']->eol_date->format('d.m.Y') }}</span>
                                            <x-eol :os="$geraet['os']" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-slot>
        </x-card>
    @empty
        <div class="m-3 rounded-xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
            {{ __('Kein Gerät läuft auf einem System, dessen Support in den nächsten sechs Monaten endet.') }}
        </div>
    @endforelse

</x-admin-layout>
