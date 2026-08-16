{{-- Knopf plus Modal. Der Knopf steht dort, wo das fehlende Netz auffaellt -
     neben der VLAN-Auswahl am Geraet oder ueber der VLAN-Liste. --}}
<div class="inline">
    @can('network_create')
        {{-- Zwei Auftritte: als Textlink neben der VLAN-Auswahl am Geraet, als
             voller Knopf im Kopf der Liste. Deshalb Klassen und Beschriftung
             von aussen statt merge() - sonst stapeln sich text-xs und text-sm. --}}
        <button type="button" wire:click="$set('offen', true)"
            class="{{ $knopfKlassen ?: 'text-xs text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400' }}">
            @if ($mitSymbol)
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            @endif
            {{ $label ?: __('+ Neues VLAN') }}
        </button>
    @endcan

    @if ($offen)
        {{-- max-h/overflow: zehn Felder passen auf kleinen Bildschirmen sonst
             nicht ins Bild. --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-on:keydown.escape.window="$wire.set('offen', false)">

            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 text-left shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">
                    {{ $bearbeiteId ? __('VLAN bearbeiten') : __('Neues VLAN') }}
                </div>

                <div class="flex flex-col gap-3">
                    {{-- Nur ohne vorgegebenen Standort: Am Geraet erbt das Netz dessen Standort. --}}
                    @if ($sites->isNotEmpty())
                        <div class="flex flex-col">
                            <x-input.label :value="__('Standort')" />
                            <x-input.select name="site_id" wire:model="site_id" class="mt-1">
                                <option value="">— {{ __('bitte wählen') }} —</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                @endforeach
                            </x-input.select>
                            @error('site_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="flex flex-col">
                        <x-input.label :value="__('Bezeichnung')" />
                        <x-input.text wire:model="description" type="text" class="mt-1" :placeholder="__('z. B. Clients')" />
                        @error('description') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-3">
                        <div class="flex w-1/3 flex-col">
                            <x-input.label :value="__('VLAN-ID')" />
                            <x-input.text wire:model="vlanId" type="number" class="mt-1" placeholder="20" />
                            @error('vlanId') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('Netz')" />
                            <x-input.text wire:model="network" type="text" class="mt-1" placeholder="10.10.20.0" />
                            @error('network') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('Subnetzmaske')" />
                            <x-input.text wire:model="subnetmask" type="text" class="mt-1" />
                            @error('subnetmask') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex w-1/3 flex-col">
                            <x-input.label :value="__('CIDR')" />
                            <x-input.text wire:model="cidr" type="number" class="mt-1" placeholder="24" />
                            @error('cidr') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <x-input.label :value="__('Gateway')" />
                        <x-input.text wire:model="gateway" type="text" class="mt-1" placeholder="10.10.20.1" />
                        @error('gateway') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-3">
                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('DNS 1')" />
                            <x-input.text wire:model="dns1" type="text" class="mt-1" />
                            @error('dns1') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('DNS 2')" />
                            <x-input.text wire:model="dns2" type="text" class="mt-1" />
                            @error('dns2') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('DHCP-Start')" />
                            <x-input.text wire:model="dhcpStart" type="text" class="mt-1" />
                            @error('dhcpStart') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('DHCP-Ende')" />
                            <x-input.text wire:model="dhcpEnd" type="text" class="mt-1" />
                            @error('dhcpEnd') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
                    {{-- Nur beim Bearbeiten, und mit Rueckfrage: Der Knopf sitzt
                         neben "Speichern", da soll kein Klick daneben genuegen. --}}
                    @if ($bearbeiteId)
                        @can('network_delete')
                            <x-input.button type="button" color="red" wire:click="loeschen"
                                wire:confirm="{{ __('VLAN wirklich löschen?') }}" :label="__('Löschen')"
                                class="mr-auto" />
                        @endcan
                    @endif

                    <x-input.button type="button" color="gray" wire:click="$set('offen', false)" :label="__('Abbrechen')" />
                    <x-input.button type="button" wire:click="speichern" :label="$bearbeiteId ? __('Speichern') : __('Anlegen')" />
                </div>
            </div>
        </div>
    @endif
</div>
