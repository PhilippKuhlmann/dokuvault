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
                     die halb ausgefuellte Zeile beim Zurueckkommen weg. Dieselbe
                     Komponente steht ueber der VLAN-Liste. --}}
                <livewire:network-quick-create :customer="$kunde" :site-id="$geraeteStandort" />
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


</div>
</div>
