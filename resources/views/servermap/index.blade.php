<x-app-layout :$customer>
    <div class="p-3 sm:p-5">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Serverplan') }}</div>
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-5">
            {{ __('Cluster, Hosts und ihre Gäste – mit CPU, Kernen, Arbeitsspeicher, Betriebssystem und Adresse. Was die Agenten melden, steht von selbst da.') }}
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
                                        @include('networkmap._node', [
                                            'node' => ['name' => $column['cluster']['name'], 'type' => $column['cluster']['type'], 'url' => $column['cluster']['url'], 'detail' => implode(' · ', $column['cluster']['facts'])],
                                            'class' => 'border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-sky-900/20',
                                        ])
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
                                            @include('servermap._host-guests', ['list' => $host['guests']])
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
