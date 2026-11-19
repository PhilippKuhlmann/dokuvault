<x-app-layout :$customer>
    <div class="p-3 sm:p-5">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Serverplan') }}</div>
        <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Cluster, Hosts und ihre Gäste – mit CPU, Kernen, Arbeitsspeicher, Betriebssystem und Adresse. Was die Agenten melden, steht von selbst da.') }}
            </div>
            {{-- A link, not a script: the state sits in the address and
                 survives a reload. --}}
            <a href="{{ route('servermap.index', $runningOnly ? [$customer] : [$customer, 'running' => 1]) }}"
                class="inline-flex shrink-0 items-center gap-2 self-start rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 hover:border-cerulean-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-cerulean-500"
                role="switch" aria-checked="{{ $runningOnly ? 'true' : 'false' }}">
                <span @class(['relative inline-block h-4 w-7 rounded-full transition-colors', 'bg-cerulean-500' => $runningOnly, 'bg-gray-300 dark:bg-gray-600' => ! $runningOnly])>
                    <span @class(['absolute top-0.5 h-3 w-3 rounded-full bg-white transition-all', 'left-3.5' => $runningOnly, 'left-0.5' => ! $runningOnly])></span>
                </span>
                {{ __('Ausgeschaltete ausblenden') }}
            </a>
        </div>

        @forelse ($sites as $entry)
            <x-panel class="mb-6">
                <div class="mb-4 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ $entry['site']->name }}</div>

                @if (! $entry['columns'] && ! $entry['standalone'] && ! $entry['unhosted'])
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Für diesen Standort sind weder Server noch VMs dokumentiert.') }}
                    </div>
                @endif

                @if ($entry['columns'])
                    <div class="overflow-x-auto pb-2">
                        <div class="flex min-w-max items-start gap-10">
                            @foreach ($entry['columns'] as $column)
                                <div class="flex flex-col items-center">
                                    @if ($column['cluster'])
                                        @include('servermap._cluster', ['cluster' => $column['cluster']])
                                        @if ($column['hosts'] || $column['loose'])
                                            <div class="network-map-stem"></div>
                                            <div class="network-map-branches">
                                                @foreach ($column['hosts'] as $host)
                                                    <div class="network-map-branch">
                                                        @include('servermap._host')
                                                        @if ($host['guests'])
                                                            @include('servermap._host-guests', ['list' => $host['guests']])
                                                        @endif
                                                    </div>
                                                @endforeach
                                                @if ($column['loose'])
                                                    {{-- Guests of the cluster without a known host. --}}
                                                    <div class="network-map-branch">
                                                        @include('servermap._guests', ['list' => $column['loose'], 'title' => __('Host unbekannt')])
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        @foreach ($column['hosts'] as $host)
                                            @include('servermap._host')
                                            @if ($host['guests'])
                                                @include('servermap._host-guests', ['list' => $host['guests']])
                                            @endif
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- What has no place in the tree stays visible: a server without
                     guests is still a server, a guest without host a gap. --}}
                @if ($entry['standalone'])
                    <div @class(['text-sm', 'mt-5 border-t border-gray-100 pt-3 dark:border-gray-700' => $entry['columns']])>
                        <div class="mb-2 text-xs font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Server ohne Gäste') }}</div>
                        <div class="flex flex-wrap items-start gap-4">
                            @foreach ($entry['standalone'] as $host)
                                <div>@include('servermap._host')</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($entry['unhosted'])
                    <div @class(['text-sm', 'mt-5 border-t border-gray-100 pt-3 dark:border-gray-700' => $entry['columns'] || $entry['standalone']])>
                        <div class="mb-1 text-xs font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('VMs ohne Host') }}</div>
                        <div class="mb-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Weder Host noch Cluster eingetragen.') }}</div>
                        @include('servermap._guests', ['list' => $entry['unhosted']])
                    </div>
                @endif
            </x-panel>
        @empty
            <x-emptystate :message="__('Noch kein Standort angelegt.')" />
        @endforelse
    </div>
</x-app-layout>
