<x-app-layout :$customer>
    <div class="p-3 sm:p-5">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">{{ __('Netzplan') }}</div>
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-5">
            {{ __('Internet, Gateway, VLANs und Geräte – zusammengesetzt aus IP-Adressen und Gateways der VLANs.') }}
        </div>

        {{-- Names per group before the rest is folded into "+ n more": keeps
             the VLAN cards about the same height next to each other. --}}
        @php($shown = 5)

        @forelse ($sites as $entry)
            <x-panel class="mb-6">
                <div class="mb-4 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ $entry['site']->name }}</div>

                @if (! $entry['columns'] && ! $entry['standalone'] && ! $entry['unlinkedUplinks'] && ! $entry['unassigned'])
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Für diesen Standort sind weder Firewall, Router noch VLANs dokumentiert.') }}
                    </div>
                @endif

                <div class="overflow-x-auto pb-2">
                    <div class="flex min-w-max items-start gap-10">
                        @foreach ($entry['columns'] as $column)
                            <div class="flex flex-col items-center">
                                @foreach ($column['uplinks'] as $uplink)
                                    @include('networkmap._node', ['node' => $uplink + ['type' => 'Internet'], 'class' => 'border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-sky-900/20'])
                                    <div class="network-map-stem"></div>
                                @endforeach

                                @if ($column['gateway'])
                                    @include('networkmap._node', ['node' => $column['gateway'], 'class' => 'border-chathams-blue-200 bg-[#f3f6fb] dark:border-gray-600 dark:bg-gray-700/40'])
                                @else
                                    {{-- The VLANs below name a gateway that no documented
                                         device holds. A dashed box, so the gap is visible;
                                         the address stands on each VLAN card. --}}
                                    <div class="mx-auto w-56 rounded-lg border border-dashed border-amber-300 bg-amber-50/50 px-3 py-2 text-center dark:border-amber-700 dark:bg-amber-900/10">
                                        <div class="text-[10px] font-DINPro-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">{{ __('Gateway nicht dokumentiert') }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('Kein Gerät trägt die Gateway-Adresse.') }}</div>
                                    </div>
                                @endif

                                @if ($column['networks'])
                                    <div class="network-map-stem"></div>
                                    <div class="network-map-branches">
                                        @foreach ($column['networks'] as $item)
                                            @php($network = $item['network'])
                                            <div class="network-map-branch">
                                                <div class="w-60 overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                                                    <div class="border-b border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-700/40">
                                                        <div class="flex items-baseline justify-between gap-2">
                                                            <div class="truncate font-DINPro-bold text-gray-900 dark:text-gray-100" title="{{ $network->description }}">{{ $network->description ?: __('VLAN') }}</div>
                                                            @if ($network->vlanId)
                                                                <span class="shrink-0 text-xs text-gray-400">VLAN {{ $network->vlanId }}</span>
                                                            @endif
                                                        </div>
                                                        <div class="flex justify-between gap-2 font-mono text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                                            <span>{{ $network->network }}/{{ $network->cidr ?: '?' }}</span>
                                                            <span class="truncate" title="{{ __('Gateway') }}">{{ $network->gateway ? 'GW '.$network->gateway : __('kein Gateway') }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="space-y-2 px-3 py-2 text-sm">
                                                        @forelse ($item['groups'] as $group)
                                                            <div>
                                                                <div class="text-[10px] font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __($group['label']) }} · {{ count($group['devices']) }}</div>
                                                                @foreach (array_slice($group['devices'], 0, $shown) as $device)
                                                                    <div class="flex items-baseline justify-between gap-2">
                                                                        @if ($device['url'])
                                                                            <a href="{{ $device['url'] }}" class="truncate text-gray-800 hover:text-cerulean-600 dark:text-gray-200 dark:hover:text-cerulean-400" title="{{ $device['name'] }}">{{ $device['name'] }}</a>
                                                                        @else
                                                                            <span class="truncate text-gray-800 dark:text-gray-200" title="{{ $device['name'] }}">{{ $device['name'] }}</span>
                                                                        @endif
                                                                        <span class="shrink-0 font-mono text-xs text-gray-400 tabular-nums">{{ $device['address'] }}</span>
                                                                    </div>
                                                                @endforeach
                                                                @if (count($group['devices']) > $shown)
                                                                    <a href="{{ route('ipplan.index', $customer) }}" class="text-xs text-gray-500 hover:text-cerulean-600 dark:text-gray-400 dark:hover:text-cerulean-400">
                                                                        {{ __('+ :count weitere', ['count' => count($group['devices']) - $shown]) }}
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @empty
                                                            <div class="text-xs italic text-gray-400 dark:text-gray-500">{{ __('Keine Geräte mit Adresse in diesem Netz') }}</div>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- What could not be placed stays visible: otherwise a documented
                     connection or device would be in the docs but not on the map. --}}
                @if ($entry['standalone'])
                    <div class="mt-5 border-t border-gray-100 pt-3 text-sm dark:border-gray-700">
                        <div class="mb-1 text-xs font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Weitere Firewalls und Router') }}</div>
                        <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Sie tragen weder ein VLAN-Gateway noch die WAN-IP eines Anschlusses.') }}</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            @foreach ($entry['standalone'] as $device)
                                <span class="text-gray-800 dark:text-gray-200">
                                    @if ($device['url'])<a href="{{ $device['url'] }}" class="hover:text-cerulean-600 dark:hover:text-cerulean-400">{{ $device['name'] }}</a>@else{{ $device['name'] }}@endif
                                    <span class="text-xs text-gray-400">{{ __($device['type']) }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($entry['unlinkedUplinks'])
                    <div class="mt-5 border-t border-gray-100 pt-3 text-sm dark:border-gray-700">
                        <div class="mb-1 text-xs font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Internetanschlüsse ohne Zuordnung') }}</div>
                        <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Keine Firewall und kein Router trägt die WAN-IP, und am Standort steht nicht genau eines dieser Geräte.') }}</div>
                        @foreach ($entry['unlinkedUplinks'] as $uplink)
                            <div class="text-gray-800 dark:text-gray-200">
                                @if ($uplink['url'])<a href="{{ $uplink['url'] }}" class="hover:text-cerulean-600 dark:hover:text-cerulean-400">{{ $uplink['name'] }}</a>@else{{ $uplink['name'] }}@endif
                                @if ($uplink['detail'])<span class="text-gray-500 dark:text-gray-400"> · {{ $uplink['detail'] }}</span>@endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($entry['unassigned'])
                    <div class="mt-5 border-t border-gray-100 pt-3 text-sm dark:border-gray-700">
                        <div class="mb-1 text-xs font-DINPro-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Geräte ohne passendes VLAN') }}</div>
                        <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Ihre Adresse liegt in keinem dokumentierten Netz.') }}</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            @foreach ($entry['unassigned'] as $device)
                                <span class="text-gray-800 dark:text-gray-200">
                                    @if ($device['url'])<a href="{{ $device['url'] }}" class="hover:text-cerulean-600 dark:hover:text-cerulean-400">{{ $device['name'] }}</a>@else{{ $device['name'] }}@endif
                                    <span class="font-mono text-xs text-gray-400">{{ $device['address'] }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-panel>
        @empty
            <x-emptystate :message="__('Noch kein Standort angelegt.')" />
        @endforelse
    </div>
</x-app-layout>
