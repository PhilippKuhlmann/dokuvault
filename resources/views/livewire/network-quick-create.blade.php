{{-- Knopf plus Modal. Der Knopf steht dort, wo das fehlende Netz auffaellt -
     neben der VLAN-Auswahl am Geraet oder ueber der VLAN-Liste. --}}
<div class="inline">
    @can('network_create')
        {{-- Zwei Auftritte: als Textlink neben der VLAN-Auswahl am Geraet, als
             voller Knopf im Kopf der Liste. Deshalb Klassen und Beschriftung
             von aussen statt merge() - sonst stapeln sich text-xs und text-sm. --}}
        <button type="button" wire:click="neu"
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
            x-on:keydown.escape.window="$wire.abbrechen()">

            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl border border-gray-200 bg-white px-5 pt-5 text-left shadow-lg dark:border-gray-700 dark:bg-gray-800">
                {{-- Bei der Rueckfrage keine Ueberschrift: Der rote Kasten stellt
                     die Frage schon, zweimal dasselbe waere Fuellmaterial. --}}
                @unless ($loeschenGefragt)
                    <div class="mb-4 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">
                        {{ $bearbeiteId ? __('VLAN bearbeiten') : __('Neues VLAN') }}
                    </div>
                @endunless

                @unless ($loeschenGefragt)
                <x-input.fehlerliste />

                <div class="flex flex-col gap-3">
                    {{-- Nur ohne vorgegebenen Standort: Am Geraet erbt das Netz dessen Standort. --}}
                    @if ($sites->isNotEmpty())
                        <div class="flex flex-col">
                            <x-input.label :value="__('Standort')" />
                            <x-input.select name="site_id" feld="site_id" wire:model="site_id" class="mt-1">
                                <option value="">— {{ __('bitte wählen') }} —</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                @endforeach
                            </x-input.select>
                            <x-input.fehler feld="site_id" />
                        </div>
                    @endif

                    <div class="flex flex-col">
                        <x-input.label :value="__('Bezeichnung')" />
                        <x-input.text feld="description" wire:model="description" type="text" class="mt-1" :placeholder="__('z. B. Clients')" />
                        <x-input.fehler feld="description" />
                    </div>

                    <div class="flex gap-3">
                        <div class="flex w-1/3 flex-col">
                            <x-input.label :value="__('VLAN-ID')" />
                            <x-input.text feld="vlanId" wire:model="vlanId" type="number" class="mt-1" placeholder="20" />
                            <x-input.fehler feld="vlanId" />
                        </div>

                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('Netz')" />
                            <x-input.text feld="network" wire:model="network" type="text" class="mt-1" placeholder="10.10.20.0" />
                            <x-input.fehler feld="network" />
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('Subnetzmaske')" />
                            {{-- .live, damit die Umrechnung ueberhaupt laeuft:
                                 .blur schreibt den Wert nur in den Browser und
                                 schickt keine Anfrage - der Hook auf dem Server
                                 feuert dann nie. Das debounce haelt die Anfragen
                                 im Zaum, halb getippte Masken ergeben ohnehin
                                 keine Zahl und lassen das Partnerfeld in Ruhe. --}}
                            <x-input.text feld="subnetmask" wire:model.live.debounce.600ms="subnetmask" type="text" class="mt-1" />
                            <x-input.fehler feld="subnetmask" />
                        </div>

                        <div class="flex w-1/3 flex-col">
                            <x-input.label :value="__('CIDR')" />
                            <x-input.text feld="cidr" wire:model.live.debounce.600ms="cidr" type="number" class="mt-1" placeholder="24" />
                            <x-input.fehler feld="cidr" />
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <x-input.label :value="__('Gateway')" />
                        <x-input.text feld="gateway" wire:model="gateway" type="text" class="mt-1" placeholder="10.10.20.1" />
                        <x-input.fehler feld="gateway" />
                    </div>

                    <div class="flex gap-3">
                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('DNS 1')" />
                            <x-input.text feld="dns1" wire:model="dns1" type="text" class="mt-1" />
                            <x-input.fehler feld="dns1" />
                        </div>

                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('DNS 2')" />
                            <x-input.text feld="dns2" wire:model="dns2" type="text" class="mt-1" />
                            <x-input.fehler feld="dns2" />
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('DHCP-Start')" />
                            <x-input.text feld="dhcpStart" wire:model="dhcpStart" type="text" class="mt-1" />
                            <x-input.fehler feld="dhcpStart" />
                        </div>

                        <div class="flex flex-1 flex-col">
                            <x-input.label :value="__('DHCP-Ende')" />
                            <x-input.text feld="dhcpEnd" wire:model="dhcpEnd" type="text" class="mt-1" />
                            <x-input.fehler feld="dhcpEnd" />
                        </div>
                    </div>
                </div>

                @endunless

                @if ($loeschenGefragt)
                    {{-- Die Rueckfrage ersetzt die Felder, statt unter ihnen zu
                         haengen: Bei zehn Feldern stand sie sonst ausserhalb des
                         Sichtbereichs und man musste erst dorthin scrollen. --}}
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/20">
                        <div class="text-sm font-medium text-red-800 dark:text-red-300">
                            {{ __('Dieses VLAN löschen?') }}
                        </div>
                        <p class="mt-1 text-xs text-red-700/80 dark:text-red-400/80">
                            {{ __('Es landet im Papierkorb und lässt sich von dort wiederherstellen.') }}
                        </p>

                        <div class="mt-4 flex justify-end gap-2">
                            <x-input.button type="button" color="gray"
                                wire:click="$set('loeschenGefragt', false)" :label="__('Abbrechen')" />
                            <x-input.button type="button" color="red" wire:click="loeschen" :label="__('Löschen')" />
                        </div>
                    </div>
                @else
                    <div class="sticky bottom-0 -mx-5 mt-5 flex flex-wrap items-center justify-end gap-2 rounded-b-xl border-t border-gray-100 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
                        @if ($bearbeiteId)
                            @can('network_delete')
                                <x-input.button type="button" color="red" class="mr-auto"
                                    wire:click="$set('loeschenGefragt', true)" :label="__('Löschen')" />
                            @endcan
                        @endif

                        {{-- abbrechen() statt offen=false: sonst bleibt bearbeiteId stehen
                             und das naechste "Neu" oeffnet das Bearbeiten-Modal. --}}
                        <x-input.button type="button" color="gray" wire:click="abbrechen" :label="__('Abbrechen')" />
                        <x-input.button type="button" wire:click="speichern" :label="$bearbeiteId ? __('Speichern') : __('Anlegen')" />
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
