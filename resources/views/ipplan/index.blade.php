<x-app-layout :$customer>
    <div class="p-3 sm:p-5">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100 mb-1">IPAM</div>
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-5 font-mono tabular-nums">
            {{ $plans->count() }} {{ Str::plural('VLAN', $plans->count()) }} · {{ number_format($totalUsed, 0, ',', '.') }} von {{ number_format($totalAddresses, 0, ',', '.') }} Adressen belegt
        </div>

        @forelse ($plans as $entry)
            @php
                $network = $entry['network'];
                $plan = $entry['plan'];
                $total = max($plan['total'] ?? 1, 1);
                $counts = $plan['counts'] ?? ['device' => 0, 'dhcp' => 0, 'free' => 0, 'reserved' => 0];
                $pctDevice = $counts['device'] / $total * 100;
                $pctDhcp = $counts['dhcp'] / $total * 100;
                $reserviert = $plan['reserviert'] ?? [];
            @endphp

            <div class="mb-6 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-[#f3f6fb] dark:bg-gray-700/40 dark:border-gray-700">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">
                            {{ $network->description ?: 'VLAN' }}
                            @if ($network->vlanId)
                                <span class="text-sm text-gray-400 font-DINPro">VLAN {{ $network->vlanId }}</span>
                            @endif
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 font-mono tabular-nums">
                            {{ $network->network }}/{{ $network->cidr ?: '?' }}
                            @if (! ($plan['error'] ?? null))
                                · {{ $plan['usedCount'] }} belegt
                            @endif
                        </div>
                    </div>

                    @if (! ($plan['error'] ?? null))
                        <div class="flex h-1.5 w-full overflow-hidden rounded-full bg-gray-200/70 dark:bg-gray-600/50 mt-2.5">
                            <div class="bg-cerulean-500" style="width: {{ $counts['device'] > 0 ? 'max(3px, ' . $pctDevice . '%)' : '0' }}"></div>
                            {{-- Ein Stueck je Reservierung in ihrer Farbe, nicht ein
                                 Sammelstueck: Sonst sagt der Balken "reserviert", aber
                                 nicht, von wem - und drei Bereiche saehen aus wie einer. --}}
                            @foreach ($reserviert as $block)
                                <div class="{{ $block['farbe']['balken'] ?? '' }}" title="{{ $block['label'] }}"
                                    style="width: {{ 'max(3px, ' . ($block['anzahl'] / $total * 100) . '%)' }}"></div>
                            @endforeach
                            <div class="bg-slate-400 dark:bg-slate-500" style="width: {{ $counts['dhcp'] > 0 ? 'max(3px, ' . $pctDhcp . '%)' : '0' }}"></div>
                        </div>
                    @endif
                </div>

                @if ($plan['error'] ?? null)
                    <div class="px-5 py-4 text-sm text-amber-600 dark:text-amber-400">{{ $plan['error'] }} — bitte Netzadresse und CIDR/Subnetzmaske prüfen.</div>
                @else
                    <div class="overflow-x-auto">
                    <table class="w-full min-w-max text-sm text-left sm:min-w-0">
                        <thead class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="py-2 px-5 font-semibold w-1/3">{{ __('IP-Adresse') }}</th>
                                <th class="py-2 px-5 font-semibold">{{ __('Zuordnung') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plan['rows'] as $row)
                                <tr class="border-b border-gray-50 last:border-0 dark:border-gray-700/50
                                    @if ($row['kind'] === 'free' || $row['kind'] === 'reserved') text-gray-400 dark:text-gray-500
                                    @elseif ($row['kind'] === 'dhcp') bg-slate-50/60 dark:bg-slate-700/20
                                    @endif">
                                    <td class="py-1.5 px-5 font-mono tabular-nums whitespace-nowrap {{ $row['kind'] === 'device' ? 'text-gray-900 dark:text-gray-100' : '' }} {{ $row['kind'] === 'dhcp' ? 'border-l-2 border-slate-400 dark:border-slate-500' : '' }} {{ $row['kind'] === 'reserved' ? 'border-l-2 '.($row['farbe']['rand'] ?? '') : '' }}
                                        {{-- Eine Reservierung wird nur vorn markiert, nicht
                                             flaechig eingefaerbt - weder die freien Adressen
                                             darin noch die belegten. Der Streifen sagt, wie weit
                                             der Block reicht; ein gelber Grund ueber zehn Zeilen
                                             faellt mehr auf als das, was tatsaechlich drinsteht. --}}
                                        {{ ($row['reservierung'] ?? null) ? 'border-l-2 '.($row['farbe']['rand'] ?? '') : '' }}">
                                        @if ($row['single'])
                                            {{ $row['from'] }}
                                        @else
                                            {{ $row['from'] }} – {{ $row['to'] }}
                                        @endif
                                    </td>
                                    <td class="py-1.5 px-5">
                                        @if ($row['kind'] === 'device')
                                            @if ($row['isGateway'] ?? false)
                                                <span class="inline-flex items-center rounded bg-cerulean-50 px-1.5 py-0.5 text-[10px] font-DINPro-bold text-cerulean-700 dark:bg-cerulean-900/30 dark:text-cerulean-300 mr-1.5 align-middle">{{ __('Gateway') }}</span>
                                            @endif
                                            <span class="text-gray-900 dark:text-gray-100 align-middle">{{ $row['label'] }}</span>
                                            {{-- Eine belegte Adresse innerhalb einer Reservierung bleibt
                                                 belegt - sie zeigt zusaetzlich, wozu der Block gehoert.
                                                 Ohne das saehe der Block loechrig aus, sobald jemand
                                                 eine Adresse daraus vergibt. --}}
                                            @if ($row['reservierung'] ?? null)
                                                <span class="ml-1.5 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-DINPro-bold align-middle {{ $row['farbe']['marke'] ?? '' }}">{{ $row['reservierung'] }}</span>
                                            @endif
                                        @elseif ($row['kind'] === 'reserved')
                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-DINPro-bold {{ $row['farbe']['marke'] ?? '' }}">{{ __('reserviert') }}</span>
                                            <span class="ml-1.5 align-middle {{ $row['farbe']['text'] ?? '' }}">{{ $row['label'] }}</span>
                                        @elseif ($row['kind'] === 'dhcp')
                                            <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-DINPro-bold text-slate-600 dark:bg-slate-700/40 dark:text-slate-300">{{ $row['label'] }}</span>
                                            {{-- Welche Geraete aus dem Pool bedient werden, steht am
                                                 Pool und nicht an einer Adresse: welche sie gerade
                                                 haben, ist morgen eine andere. --}}
                                            @if (! empty($row['geraete']))
                                                <span class="ml-1.5 align-middle text-gray-500 dark:text-gray-400">{{ implode(', ', $row['geraete']) }}</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 italic">{{ $row['label'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>

                    {{-- Kein DHCP-Bereich gepflegt, aber Geraete haengen daran:
                         Dann steht es unter der Tabelle. Sie wegzulassen hiesse,
                         dass ein dokumentiertes Geraet im Plan nicht vorkommt. --}}
                    @if (! empty($plan['dhcpOhneBereich']))
                        <div class="px-5 py-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Per DHCP versorgt (kein DHCP-Bereich gepflegt):') }}
                            <span class="text-gray-700 dark:text-gray-300">{{ implode(', ', $plan['dhcpOhneBereich']) }}</span>
                        </div>
                    @endif

                    <livewire:ip-bereiche :customer="$customer" :network="$network" :key="'bereiche-'.$network->id" />

                    @if ($plan['truncated'] ?? false)
                        <div class="px-5 py-3 text-xs text-gray-400 border-t border-gray-100 dark:border-gray-700">
                            {{ __('Subnetz zu groß — nur die ersten 8.192 Adressen aufgelistet.') }}
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <div class="text-sm text-gray-400">{{ __('Keine VLANs angelegt.') }}</div>
        @endforelse
    </div>
</x-app-layout>
