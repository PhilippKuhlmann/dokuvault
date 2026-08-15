{{-- Eigenstaendig: Wrapper haelt dieselbe zentrierte Spaltenbreite wie das
     Formular darueber (x-create.main), plus eigener Kartenrahmen.
     Eingebettet: beides faellt weg, der Block sitzt in der Karte des Formulars
     und bekommt statt des Rahmens nur eine Trennlinie nach oben. --}}
<div @class([
    'mx-auto max-w-3xl px-3' => ! $eingebettet,
    'px-5 sm:px-6' => $eingebettet,
])>
<div @class([
    'my-3 p-5 sm:p-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700' => ! $eingebettet,
    'border-t border-gray-100 py-5 dark:border-gray-700' => $eingebettet,
])>
    {{-- Der Hinweis trennt diese Karte vom Formular darueber: Dort speichert ein
         Knopf am Ende, hier wirkt jede Zeile sofort. --}}
    <div class="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Weitere IP-Adressen') }}</div>
        <span class="rounded bg-cerulean-50 px-2 py-0.5 text-xs text-cerulean-700 dark:bg-cerulean-950 dark:text-cerulean-300">{{ __('speichert sofort') }}</span>
    </div>

    @if ($entries->isNotEmpty())
        <table class="w-full text-sm mb-4">
            <thead class="text-xs uppercase tracking-wide text-gray-400 border-b border-gray-100 dark:border-gray-700">
                <tr>
                    <th class="py-2 pr-4 text-left font-semibold">{{ __('IP-Adresse') }}</th>
                    <th class="py-2 pr-4 text-left font-semibold">VLAN</th>
                    <th class="py-2 pr-4 text-left font-semibold">{{ __('Bezeichnung') }}</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr class="border-b border-gray-50 last:border-0 dark:border-gray-700/50">
                        <td class="py-2 pr-4 font-mono text-gray-900 dark:text-gray-100">{{ $entry->address }}</td>
                        <td class="py-2 pr-4 text-gray-600 dark:text-gray-300">
                            {{ $entry->network?->anzeige() ?: '—' }}
                        </td>
                        <td class="py-2 pr-4 text-gray-600 dark:text-gray-300">{{ $entry->label ?: '—' }}</td>
                        <td class="py-2 text-right">
                            <button type="button" wire:click="remove({{ $entry->id }})"
                                wire:confirm="IP-Adresse entfernen?"
                                class="text-red-600 hover:text-red-700 text-sm">{{ __('Entfernen') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="text-sm text-gray-400 dark:text-gray-500 mb-4">{{ __('Noch keine zusätzlichen IP-Adressen.') }}</div>
    @endif

    <div class="flex flex-wrap items-end gap-2" x-data>
        <div class="flex flex-col">
            <x-input.label :value="__('IP-Adresse')" />
            <x-input.text wire:model="address" x-ref="addr" type="text" class="mt-1 w-40" placeholder="10.10.30.1" />
            @error('address') <span class="text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
        </div>
        {{-- min-w-0 + max-w-full: das Select waechst sonst auf die Breite der laengsten
             Option ("Beschreibung (10.10.30.0/24)") und schiebt die Seite auf Mobil seitlich raus --}}
        <div class="flex flex-col min-w-0 max-w-full">
            <div class="flex items-baseline gap-2">
                <x-input.label :value="__('VLAN (optional)')" />

                {{-- Fehlt das Netz, soll man es hier anlegen koennen: Sonst ist
                     die halb ausgefuellte Zeile beim Zurueckkommen weg. --}}
                @can('network_create')
                    <button type="button" wire:click="$set('vlanModal', true)"
                        class="text-xs text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">
                        {{ __('+ Neues VLAN') }}
                    </button>
                @endcan
            </div>
            {{-- Bei Auswahl eines VLANs das IP-Feld mit dem Netz-Präfix (erste 3 Oktette) vorbefüllen;
                 ein bereits eingegebenes letztes Oktett bleibt erhalten. --}}
            <x-input.select name="network_id" wire:model="network_id" class="mt-1 max-w-full"
                x-on:change="
                    const prefix = $event.target.selectedOptions[0]?.dataset.prefix || '';
                    if (prefix) {
                        const parts = $refs.addr.value.split('.');
                        const host = parts.length === 4 ? parts[3] : '';
                        $refs.addr.value = prefix + host;
                        $refs.addr.dispatchEvent(new Event('input'));
                    }
                ">
                <option value="">— kein VLAN —</option>
                @foreach ($networks as $network)
                    @php
                        $octets = explode('.', (string) $network->network);
                        $prefix = count($octets) === 4 ? $octets[0] . '.' . $octets[1] . '.' . $octets[2] . '.' : '';
                    @endphp
                    <option value="{{ $network->id }}" data-prefix="{{ $prefix }}">{{ $network->anzeige() }} ({{ $network->network }}/{{ $network->cidr }})</option>
                @endforeach
            </x-input.select>
        </div>
        <div class="flex flex-col">
            <x-input.label :value="__('Bezeichnung (optional)')" />
            <x-input.text wire:model="label" type="text" class="mt-1 w-48" :placeholder="__('z. B. Gateway')" />
        </div>
        <x-input.button type="button" size="feld" wire:click="add" :label="__('Hinzufügen')" />
    </div>

    {{-- VLAN schnell anlegen. Nur die Pflichtangaben: Gateway, DNS und DHCP
         traegt man spaeter im richtigen VLAN-Formular nach. Nach dem Speichern
         ist das neue Netz oben ausgewaehlt, damit man dort weitermacht, wo man
         aufgehoert hat. --}}
    @if ($vlanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            wire:key="vlan-modal" x-on:keydown.escape.window="$wire.set('vlanModal', false)">

            <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">
                    {{ __('Neues VLAN') }}
                </div>

                <div class="flex flex-col gap-3">
                    <div class="flex flex-col">
                        <x-input.label :value="__('Bezeichnung')" />
                        <x-input.text wire:model="vlanDescription" type="text" class="mt-1" :placeholder="__('z. B. Clients')" />
                        @error('vlanDescription') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-3">
                        <div class="flex w-1/3 flex-col">
                            <x-input.label :value="__('VLAN-ID')" />
                            <x-input.text wire:model="vlanNummer" type="number" class="mt-1" placeholder="20" />
                            @error('vlanNummer') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('Netz')" />
                            <x-input.text wire:model="vlanNetwork" type="text" class="mt-1" placeholder="10.10.20.0" />
                            @error('vlanNetwork') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <x-input.label :value="__('Subnetzmaske')" />
                        <x-input.text wire:model="vlanSubnetmask" type="text" class="mt-1" />
                        @error('vlanSubnetmask') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <x-input.button type="button" color="gray" wire:click="$set('vlanModal', false)" :label="__('Abbrechen')" />
                    <x-input.button type="button" wire:click="vlanAnlegen" :label="__('Anlegen und auswählen')" />
                </div>
            </div>
        </div>
    @endif

</div>
</div>
