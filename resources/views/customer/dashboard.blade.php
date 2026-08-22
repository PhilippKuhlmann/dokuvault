<x-app-layout :$customer>
    <div class="p-3 sm:p-5">

        <div class="flex items-center justify-between mb-5">
            <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">
                {{ $customer->name }}
            </div>
            @can('create_pdf')
                {{-- Knopf und Stand in einer Komponente: Das PDF entsteht im
                     Hintergrund, ohne Anzeige waere nach dem Klick nichts zu
                     sehen. --}}
                <livewire:pdf-export-status :customer="$customer" />
            @endcan
        </div>

        @php $wizardPermissions = collect(config('custom.wizard_steps'))->pluck('permission')->all(); @endphp
        @canany($wizardPermissions)
            @if ($openWizardRun || $inventoryCount <= 2)
                <a href="{{ route('wizard.index', $customer) }}"
                    class="flex items-center justify-between gap-3 p-4 mb-5 rounded-xl border border-cerulean-200 bg-cerulean-50 shadow-sm transition hover:border-cerulean-400 dark:bg-cerulean-900/10 dark:border-cerulean-800 dark:hover:border-cerulean-600">
                    <div>
                        <div class="font-DINPro-bold text-cerulean-900 dark:text-cerulean-200">
                            {{ $openWizardRun ? __('Erstaufnahme fortsetzen') : __('Erstaufnahme starten') }}
                        </div>
                        <div class="text-sm text-cerulean-700 dark:text-cerulean-400">
                            {{ $openWizardRun ? __('Ein Durchlauf ist noch offen — weiter geht es dort, wo du aufgehört hast.') : __('Der Assistent fragt Standort, Netzwerk, Server und mehr Schritt für Schritt ab.') }}
                        </div>
                    </div>
                    <span class="shrink-0 px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold">
                        {{ $openWizardRun ? __('Fortsetzen') : __('Starten') }}
                    </span>
                </a>
            @endif
        @endcanany

        {{-- Inventar-Übersicht.

             Kompakter als zuvor: Seit auch Firewall, Router, Switches,
             Accesspoints, Schraenke und Patchfelder mitzaehlen, sind es
             siebzehn Kacheln statt zehn - in der alten Groesse fuellten sie
             den Bildschirm, bevor irgendetwas Inhaltliches kam. Die Zahlen
             sind Nachschlagewerte, keine Schlagzeilen: kleineres Symbol,
             kleinere Zahl, mehr Kacheln je Zeile. --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2 mb-6">
            @foreach ($tiles as $tile)
                @can($tile['can'])
                    <a href="{{ $tile['route'] }}"
                        class="group flex items-center gap-2 px-2.5 py-2 bg-white rounded-lg border border-gray-200 shadow-sm transition hover:border-cerulean-300 hover:shadow-md dark:bg-gray-800 dark:border-gray-700 dark:hover:border-cerulean-500">
                        <span class="flex items-center justify-center w-7 h-7 rounded-md bg-cerulean-50 text-cerulean-600 transition-colors group-hover:bg-cerulean-100 dark:bg-gray-700 dark:text-cerulean-400 shrink-0">
                            <x-dynamic-component :component="$tile['icon']" class="w-4 h-4" />
                        </span>
                        <span class="flex min-w-0 flex-col">
                            <span class="text-base font-bold leading-none text-chathams-blue-800 dark:text-gray-100">{{ $tile['count'] }}</span>
                            {{-- Lange Beschriftungen wie "Serverschränke" passen
                                 in der schmalen Kachel nur gekuerzt; der volle
                                 Text steht im title. --}}
                            <span class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400"
                                title="{{ __($tile['label']) }}">{{ __($tile['label']) }}</span>
                        </span>
                    </a>
                @endcan
            @endforeach
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 mb-5">

            {{-- Ablaufende Lizenzen --}}
            @can('licensesoftware_viewAny')
                <div class="col-span-2 p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-4">{{ __('Ablaufende Lizenzen') }}</div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($expiringLicenses as $license)
                            @php
                                $end = \Carbon\Carbon::parse($license->end_date)->startOfDay();
                                $days = now()->startOfDay()->diffInDays($end, false);
                            @endphp
                            <a href="{{ route('licensesoftware.index', $customer) }}"
                                class="flex items-center justify-between py-2.5 -mx-2 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <span class="text-gray-800 dark:text-gray-100">{{ $license->name }}</span>
                                @if ($days < 0)
                                    <span class="text-sm font-medium text-red-600 dark:text-red-400">abgelaufen</span>
                                @elseif ($days == 0)
                                    <span class="text-sm font-medium text-red-600 dark:text-red-400">heute</span>
                                @elseif ($days <= 14)
                                    <span class="text-sm font-medium text-amber-600 dark:text-amber-400">in {{ $days }} Tagen</span>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">in {{ $days }} Tagen</span>
                                @endif
                            </a>
                        @empty
                            <div class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Keine ablaufenden Lizenzen 🎉') }}</div>
                        @endforelse
                    </div>
                </div>
            @endcan

            {{-- Ablaufende Zertifikate --}}
            @can('certificate_viewAny')
                <div class="col-span-2 p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-4">{{ __('Ablaufende Zertifikate') }}</div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($expiringCertificates as $certificate)
                            @php
                                $end = \Carbon\Carbon::parse($certificate->expiry_date)->startOfDay();
                                $days = now()->startOfDay()->diffInDays($end, false);
                            @endphp
                            <a href="{{ route('certificate.index', $customer) }}"
                                class="flex items-center justify-between py-2.5 -mx-2 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <span class="text-gray-800 dark:text-gray-100">{{ $certificate->name }}</span>
                                @if ($days < 0)
                                    <span class="text-sm font-medium text-red-600 dark:text-red-400">abgelaufen</span>
                                @elseif ($days == 0)
                                    <span class="text-sm font-medium text-red-600 dark:text-red-400">heute</span>
                                @elseif ($days <= 14)
                                    <span class="text-sm font-medium text-amber-600 dark:text-amber-400">in {{ $days }} Tagen</span>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">in {{ $days }} Tagen</span>
                                @endif
                            </a>
                        @empty
                            <div class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Keine ablaufenden Zertifikate 🎉') }}</div>
                        @endforelse
                    </div>
                </div>
            @endcan

            {{-- Ablaufende Garantien.

                 Über alle Gerätearten hinweg: Die Frage "ist die Kiste noch in
                 Garantie?" stellt sich nicht je Liste, sondern beim Kunden. --}}
            <div class="col-span-2 p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-4">{{ __('Ablaufende Garantien') }}</div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($expiringWarranties as $garantie)
                        <a href="{{ $garantie['url'] }}"
                            class="flex items-center justify-between gap-3 py-2.5 -mx-2 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <span class="min-w-0">
                                <span class="block truncate text-gray-800 dark:text-gray-100">{{ $garantie['name'] }}</span>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ __($garantie['art']) }}</span>
                            </span>
                            @if ($garantie['tage'] < 0)
                                <span class="shrink-0 text-sm font-medium text-red-600 dark:text-red-400">abgelaufen</span>
                            @elseif ($garantie['tage'] == 0)
                                <span class="shrink-0 text-sm font-medium text-red-600 dark:text-red-400">heute</span>
                            @elseif ($garantie['tage'] <= 14)
                                <span class="shrink-0 text-sm font-medium text-amber-600 dark:text-amber-400">in {{ $garantie['tage'] }} Tagen</span>
                            @else
                                <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">in {{ $garantie['tage'] }} Tagen</span>
                            @endif
                        </a>
                    @empty
                        <div class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Keine ablaufenden Garantien 🎉') }}</div>
                    @endforelse
                </div>
            </div>

        </div>

        <div class="flex flex-wrap gap-5">

            {{-- Standorte --}}
            <div class="w-full sm:w-80 p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-4">{{ __('Standorte') }}</div>
                <div class="space-y-4">
                    @forelse ($sites as $site)
                        <div>
                            <div class="text-lg text-gray-900 dark:text-gray-100">{{ $site->name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $site->street }} {{ $site->house_number }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $site->zip }} {{ $site->city }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Keine Standorte') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Ansprechpartner --}}
            <div class="w-full sm:w-80 p-5 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-4">{{ __('Ansprechpartner') }}</div>
                <div class="space-y-4">
                    @forelse ($contactpersons as $contactperson)
                        <div>
                            <div class="text-lg text-gray-900 dark:text-gray-100">{{ $contactperson->first_name }} {{ $contactperson->last_name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $contactperson->phone }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $contactperson->mail }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Keine Ansprechpartner') }}</div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
