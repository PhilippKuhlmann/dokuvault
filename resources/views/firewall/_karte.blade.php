{{-- Eine Firewall in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->anzeige();
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="firewall_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'firewall', id: {{ $eintrag->id }} })">
                    {{ $eintrag->name }}

                    {{-- Was man an einer Firewall fast immer sucht: die Adresse,
                         der Einbauort und ob die Subscription noch laeuft. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-ip-anzeige :adresse="$adressen->first()" />
                            </x-kernwert>
                        @endif

                        @if ($eintrag->firmware)
                            <x-kernwert :label="__('Firmware')">{{ $eintrag->firmware }}</x-kernwert>
                        @endif

                        @if ($eintrag->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $eintrag->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-ipcard :device="$eintrag" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $eintrag->manufacturer,
                    'Modell' => $eintrag->model,
                    'Seriennummer' => $eintrag->serialNumber,
                    'Firmware' => $eintrag->firmware,
                    'Bauform' => __(config('custom.firewall_form_factors')[$eintrag->form_factor] ?? ''),
                ]" />

                <x-credentialscard :device="$eintrag" />

                <x-minitablecard :title="__('Zugang')" :array="[
                    'Oberfläche' => $eintrag->management_url,
                    'Port' => $eintrag->port,
                ]" />

                {{-- Die Karte blendet sich selbst aus, wenn nichts gefuellt ist -
                     bei einer Sophos bleiben diese vier Felder leer. Die
                     Beschriftungen sind so gewaehlt, dass minitablecard die
                     Geheimnisse maskiert. --}}
                <x-minitablecard :title="__('Securepoint')" :modell="$eintrag" :array="[
                    'USC-PIN' => $eintrag->usc_pin,
                    'Cloud Backup Passwort' => $eintrag->cloud_backup_password,
                    'User URL' => $eintrag->url_user,
                    'Externe URL' => $eintrag->url_external,
                ]" />

                <x-minitablecard :title="__('Subscription')" :array="[
                    'Läuft bis' => $eintrag->subscription_until?->format('d.m.Y'),
                ]" />

                {{-- What the firewall agent reported - read only, replaced by
                     every run. Each list is left out when it is empty. --}}
                @if ($eintrag->agent_reported_at)
                    @php
                        $details = $eintrag->agent_details ?? [];
                        $listen = [
                            __('Schnittstellen') => collect($details['interfaces'] ?? [])->map(fn ($i) => [
                                $i['name'], trim(($i['ip'] ?? '').(! empty($i['vlan']) ? ' · VLAN '.$i['vlan'] : '')),
                            ]),
                            __('VPNs') => collect($details['vpns'] ?? [])->map(fn ($v) => [
                                $v['name'], trim($v['type'].(! empty($v['remote']) ? ' · '.$v['remote'] : '')),
                            ]),
                            __('Portweiterleitungen') => collect($details['port_forwards'] ?? [])->map(fn ($p) => [
                                trim(($p['protocol'] ?? '').' '.($p['port'] ?? '')).(! empty($p['description']) ? ' · '.$p['description'] : ''),
                                trim(($p['target'] ?? '').(! empty($p['target_port']) ? ':'.$p['target_port'] : '')),
                            ]),
                            __('Gateways') => collect($details['gateways'] ?? [])->map(fn ($g) => [$g['name'], $g['address'] ?? '']),
                        ];
                    @endphp

                    @foreach ($listen as $titel => $zeilen)
                        @if ($zeilen->isNotEmpty())
                            <div class="w-full mb-5 break-inside-avoid">
                                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $titel }}</div>
                                <table class="w-full text-sm dark:text-gray-100">
                                    @foreach ($zeilen as [$links, $rechts])
                                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                                            <td class="py-1 pr-6 align-top text-gray-500 dark:text-gray-400">{{ $links }}</td>
                                            <td class="py-1 break-all">{{ $rechts }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        @endif
                    @endforeach

                    <p class="w-full mb-5 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Vom Agent gemeldet am :datum', ['datum' => \App\Support\Zeit::anzeigen($eintrag->agent_reported_at)]) }}
                    </p>
                @endif

                <x-minitablecard :title="__('Notizen')" :array="[
                    'Notizen' => $eintrag->notes,
                ]" />

                <x-beschaffungcard :device="$eintrag" />

            </x-slot>
        </x-card>
    
