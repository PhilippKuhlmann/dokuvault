{{-- Eine Firewall in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->label();
            $anzahlIps = $adressen->count();
        @endphp
        <x-card :raster="$eintrag->agent_reported_at ? 3 : 2">
            <x-slot:head>
                <x-show.header can="firewall_update" editAction="$dispatch('object-edit', { typ: 'firewall', id: {{ $eintrag->id }} })">
                    @php
                        $geraet = implode(' · ', array_filter([
                            trim($eintrag->manufacturer.' '.$eintrag->model),
                            __(config('custom.firewall_form_factors')[$eintrag->form_factor] ?? ''),
                        ]));
                    @endphp
                    <span class="shrink-0 font-bold">{{ $eintrag->name }}</span>

                    {{-- Wie beim Server (design 1A): feste Spalten, die Beschriftung
                         ueber dem Wert, ein fehlender Wert zeigt einen Strich. Was
                         man an einer Firewall fast immer sucht: die Adresse, den
                         Firmware-Stand und wo sie steht. --}}
                    <x-slot:kernwerte>
                        <x-kernwert :label="__('IP')" :zaehler="max(0, $anzahlIps - 1)" breite="w-36">
                            @if ($primaer)
                                <x-ip-anzeige :adresse="$adressen->first()" />
                            @endif
                        </x-kernwert>

                        <x-kernwert :label="__('Firmware')" breite="w-36" :titel="$eintrag->firmware">{{ $eintrag->firmware }}</x-kernwert>

                        <x-kernwert :label="__('Ort')" breite="w-36" :titel="$eintrag->mountLocation() ?? $eintrag->site?->name">{{ $eintrag->mountLocationShort() ?? $eintrag->site?->name }}</x-kernwert>
                    </x-slot>

                    <x-slot:aktionen>
                        <livewire:copy-password-button :device="$eintrag" :key="'kopf-kennwort-firewall-'.$eintrag->id" />
                    </x-slot>

                    {{-- Second line: the device - model and form factor. --}}
                    @if ($geraet !== '')
                        <x-slot:untertitel><span class="min-w-0 truncate" title="{{ $geraet }}">{{ $geraet }}</span></x-slot>
                    @endif
                </x-show.header>
            </x-slot>

            <x-slot:body>
                {{-- Feste Spalten nach Thema wie bei Server und VM (design 1A):
                     Zugang - womit man hineinkommt; Geraet - was man anfassen
                     und verlaengern muss; System - was der Agent meldet. --}}
                <x-kartenspalte :titel="__('Zugang')" symbol="svg.login">
                    <x-ipcard :device="$eintrag" />

                    <x-credentialscard :device="$eintrag" />

                    <x-minitablecard :title="__('Verwaltung')" :array="[
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
                </x-kartenspalte>

                <x-kartenspalte :titel="__('Gerät')" symbol="svg.server">
                    <x-minitablecard :title="__('Hardware')" :array="[
                        'Hersteller' => $eintrag->manufacturer,
                        'Modell' => $eintrag->model,
                        'Seriennummer' => $eintrag->serialNumber,
                        'Firmware' => $eintrag->firmware,
                        'Bauform' => __(config('custom.firewall_form_factors')[$eintrag->form_factor] ?? ''),
                    ]" />

                    <x-minitablecard :title="__('Subscription')" :array="[
                        'Läuft bis' => $eintrag->subscription_until?->format('d.m.Y'),
                    ]" />

                    <x-beschaffungcard :device="$eintrag" />

                    <x-minitablecard :title="__('Notizen')" :array="[
                        'Notizen' => $eintrag->notes,
                    ]" />
                </x-kartenspalte>

                @if ($eintrag->agent_reported_at)
                    <x-kartenspalte :titel="__('System')" symbol="svg.settings">
                    {{-- What the firewall agent reported - read only, replaced by
                         every run. Each list is left out when it is empty. --}}
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
                            {{ __('Vom Agent gemeldet am :datum', ['datum' => \App\Support\LocalTime::display($eintrag->agent_reported_at)]) }}
                        </p>
                    </x-kartenspalte>
                @endif

            </x-slot>
        </x-card>
    
