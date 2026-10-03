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
            @if ($openWizardRun || ($inventoryCount <= 2 && ! $wizardCompleted))
                {{-- Kein umschließender Link mehr: das Formular für "Als erledigt
                     markieren" darf nicht in einem <a> stecken. --}}
                <div class="flex flex-wrap items-center justify-between gap-3 p-4 mb-5 rounded-xl border border-cerulean-200 bg-cerulean-50 shadow-xs transition hover:border-cerulean-400 dark:bg-cerulean-900/10 dark:border-cerulean-800 dark:hover:border-cerulean-600">
                    <a href="{{ route('wizard.index', $customer) }}" class="min-w-0 flex-1">
                        <div class="font-DINPro-bold text-cerulean-900 dark:text-cerulean-200">
                            {{ $openWizardRun ? __('Erstaufnahme fortsetzen') : __('Erstaufnahme starten') }}
                        </div>
                        <div class="text-sm text-cerulean-700 dark:text-cerulean-400">
                            {{ $openWizardRun ? __('Ein Durchlauf ist noch offen — weiter geht es dort, wo du aufgehört hast.') : __('Der Assistent fragt Standort, Netzwerk, Server und mehr Schritt für Schritt ab.') }}
                        </div>
                    </a>
                    <div class="flex shrink-0 items-center gap-2">
                        <form method="POST" action="{{ route('wizard.complete', $customer) }}">
                            @csrf
                            <button type="submit"
                                class="px-4 py-2 rounded-lg border border-cerulean-300 bg-white text-cerulean-700 text-sm font-DINPro-bold transition-colors hover:bg-cerulean-100 dark:bg-transparent dark:border-cerulean-700 dark:text-cerulean-300 dark:hover:bg-cerulean-900/30">
                                {{ __('Als erledigt markieren') }}
                            </button>
                        </form>
                        <a href="{{ route('wizard.index', $customer) }}"
                            class="px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold transition-colors hover:bg-cerulean-700">
                            {{ $openWizardRun ? __('Fortsetzen') : __('Starten') }}
                        </a>
                    </div>
                </div>
            @endif
        @endcanany

        {{-- Inventar-Übersicht.

             Die Zahlen sind Nachschlagewerte, keine Schlagzeilen: kleines
             Symbol, kleine Zahl. Höchstens zwölf (zwei Zeilen à sechs), die
             Auswahl steht im CustomerController - mit allen Typen wurde die
             Leiste zur Zahlenwand. --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2 mb-6">
            @foreach ($tiles as $tile)
                @can($tile['can'])
                    <a href="{{ $tile['route'] }}"
                        class="group flex items-center gap-2 px-2.5 py-2 bg-white rounded-lg border border-gray-200 shadow-xs transition hover:border-cerulean-300 hover:shadow-md dark:bg-gray-800 dark:border-gray-700 dark:hover:border-cerulean-500">
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

        {{-- One grid for all five tiles, each the same size (x-dashboard-tile).
             Before, expiry lists and sites/contacts stood in two different
             layouts with different widths, and every tile was as tall as its
             content. --}}
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">

            {{-- Ablaufende Lizenzen --}}
            @can('licensesoftware_viewAny')
                <x-dashboard-tile :title="__('Ablaufende Lizenzen')">
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($expiringLicenses as $license)
                            @php
                                $end = \Carbon\Carbon::parse($license->end_date)->startOfDay();
                                $days = now()->startOfDay()->diffInDays($end, false);
                            @endphp
                            <a href="{{ route('licensesoftware.index', [$customer, 'highlight' => $license->id]) }}"
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
                </x-dashboard-tile>
            @endcan

            {{-- Ablaufende Zertifikate --}}
            @can('certificate_viewAny')
                <x-dashboard-tile :title="__('Ablaufende Zertifikate')">
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($expiringCertificates as $certificate)
                            @php
                                $end = \Carbon\Carbon::parse($certificate->expiry_date)->startOfDay();
                                $days = now()->startOfDay()->diffInDays($end, false);
                            @endphp
                            <a href="{{ route('certificate.index', [$customer, 'highlight' => $certificate->id]) }}"
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
                </x-dashboard-tile>
            @endcan

            {{-- Ablaufende Garantien.

                 Über alle Gerätearten hinweg: Die Frage "ist die Kiste noch in
                 Garantie?" stellt sich nicht je Liste, sondern beim Kunden. --}}
            <x-dashboard-tile :title="__('Ablaufende Garantien')">
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
            </x-dashboard-tile>

            {{-- Support ending: hardware and operating systems. Same row shape
                 as the warranties next to it. --}}
            <x-dashboard-tile :title="__('Support-Ende')">
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($endOfSupport as $eol)
                        <a href="{{ $eol['url'] }}"
                            class="flex items-center justify-between gap-3 py-2.5 -mx-2 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <span class="min-w-0">
                                <span class="block truncate text-gray-800 dark:text-gray-100">{{ $eol['name'] }}</span>
                                <span class="block truncate text-xs text-gray-400 dark:text-gray-500">{{ $eol['art'] }}</span>
                            </span>
                            @if ($eol['tage'] < 0)
                                <span class="shrink-0 text-sm font-medium text-red-600 dark:text-red-400">{{ __('abgelaufen') }}</span>
                            @elseif ($eol['tage'] == 0)
                                <span class="shrink-0 text-sm font-medium text-red-600 dark:text-red-400">{{ __('heute') }}</span>
                            @elseif ($eol['tage'] <= 90)
                                <span class="shrink-0 text-sm font-medium text-amber-600 dark:text-amber-400">{{ __('in :tage Tagen', ['tage' => $eol['tage']]) }}</span>
                            @else
                                <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ $eol['datum']->format('m/Y') }}</span>
                            @endif
                        </a>
                    @empty
                        <div class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Alles im Support 🎉') }}</div>
                    @endforelse
                </div>
            </x-dashboard-tile>

            {{-- Standorte --}}
            <x-dashboard-tile :title="__('Standorte')">
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
            </x-dashboard-tile>

            {{-- Ansprechpartner --}}
            <x-dashboard-tile :title="__('Ansprechpartner')">
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
            </x-dashboard-tile>

            {{-- Agents: only for those who manage them, and only where there
                 are agents or tokens. Everything links to the agent page. --}}
            @if ($agentWarnings !== null)
                <x-dashboard-tile :title="__('Agenten')">
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($agentWarnings as $warnung)
                            <a href="{{ route('agent.index', $customer) }}"
                                class="flex items-center justify-between gap-3 py-2.5 -mx-2 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <span class="min-w-0">
                                    <span class="block truncate text-gray-800 dark:text-gray-100">{{ $warnung['name'] }}</span>
                                    <span class="block truncate text-xs text-gray-400 dark:text-gray-500">{{ $warnung['art'] }}</span>
                                </span>
                                <span @class([
                                    'shrink-0 text-sm font-medium',
                                    'text-red-600 dark:text-red-400' => $warnung['schwer'],
                                    'text-amber-600 dark:text-amber-400' => ! $warnung['schwer'],
                                ])>{{ $warnung['text'] }}</span>
                            </a>
                        @empty
                            <div class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Alle Agenten melden 🎉') }}</div>
                        @endforelse
                    </div>
                </x-dashboard-tile>
            @endif

            {{-- Latest changes. A full row: seven tiles fill 3+3+1 or
                 2+2+2+1 columns, and the list reads better wide. With the
                 agents tile there are eight: 3+3+2 at xl, and at md the eight
                 fill four rows by themselves. --}}
            <x-dashboard-tile :title="__('Zuletzt geändert')"
                :class="$agentWarnings === null ? 'md:col-span-2 xl:col-span-3' : 'xl:col-span-2'">
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($recentChanges as $aenderung)
                        <a @if ($aenderung['url']) href="{{ $aenderung['url'] }}" @endif
                            class="flex items-center justify-between gap-3 py-2.5 -mx-2 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <span class="min-w-0">
                                <span class="block truncate text-gray-800 dark:text-gray-100">{{ $aenderung['name'] }}</span>
                                <span class="block truncate text-xs text-gray-400 dark:text-gray-500">
                                    {{ $aenderung['art'] }} ·
                                    {{ match ($aenderung['ereignis']) {
                                        'created' => __('angelegt'),
                                        'deleted' => __('gelöscht'),
                                        'restored' => __('wiederhergestellt'),
                                        default => __('geändert'),
                                    } }}
                                    @if ($aenderung['wer'])
                                        {{ __('von') }} {{ $aenderung['wer'] }}
                                    @endif
                                </span>
                            </span>
                            <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400" title="{{ $aenderung['wann']->format('d.m.Y H:i') }}">
                                {{ $aenderung['wann']->diffForHumans() }}
                            </span>
                        </a>
                    @empty
                        <div class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Änderungen protokolliert.') }}</div>
                    @endforelse
                </div>
            </x-dashboard-tile>

        </div>
    </div>
</x-app-layout>
