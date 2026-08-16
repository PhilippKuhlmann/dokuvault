<div>

    {{-- Statt des eingebauten "Neu" (fuehrt auf eine eigene Seite) dasselbe
         Modal wie im IP-Block am Geraet - man bleibt in der Liste. Der Standort
         wird hier mit abgefragt, anders als am Geraet gibt ihn nichts vor. --}}
    {{-- Titel ausdruecklich: aus dem Routennamen abgeleitet waere er beim
         Livewire-Rerender leer. --}}
    <x-sitetopmenu can="network_create" :neu="false" :titel="__(config('custom.list_titles')['network'])">
        {{-- Sucht waehrend des Tippens; .debounce haelt die Anfragen im Zaum.
             Kein autofocus - die Seite ist eine Liste, kein Suchformular. --}}
        <label class="relative">
            <span class="sr-only">{{ __('VLANs durchsuchen') }}</span>
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
            <x-input.field wire:model.live.debounce.300ms="search" type="search"
                :placeholder="__('Suche')"
                {{-- Kein eigenes rounded: x-input.field merged Klassen statt sie
                     zu ersetzen, zwei Radius-Klassen wuerden sich streiten. --}}
                class="w-56 pl-9 pr-3 py-2 text-sm" />
        </label>

        {{-- Exakt die Klassen des bisherigen "Neu" aus x-sitetopmenu. --}}
        <livewire:network-quick-create :customer="$customer" :label="__('Neu')" :mit-symbol="true" 
            knopf-klassen="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold shadow-sm hover:bg-cerulean-700 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors" />
    </x-sitetopmenu>


    @forelse ($networks as $network)
        <x-card>
            <x-slot:head>
                {{-- Stift oeffnet das Modal statt einer eigenen Seite - wie das Anlegen. --}}
                <x-show.header can="network_update" :edit-action="'$dispatch(\'vlan-bearbeiten\', { id: '.$network->id.' })'">
                    VLAN {{ $network->vlanId }} - {{ $network->description }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Netzwerk' => $network->network,
                    'Subnetzmakske' => $network->subnetmask,
                    'CIDR' => '/'. $network->cidr,
                    'Gateway' => $network->gateway,
                ]" />

                <x-minitablecard :title="__('DHCP')" :array="[
                    'Start' => $network->dhcpStart,
                    'Ende' => $network->dhcpEnd,
                ]" />

                <x-minitablecard :title="__('DNS')" :array="[
                    'DNS 1' => $network->dns1,
                    'DNS 2' => $network->dns2,
                ]" />

            </x-slot>
        </x-card>
    @empty
        {{-- Ohne Suchbegriff ist die Liste wirklich leer, mit Begriff hat nur
             nichts gepasst - das ist ein Unterschied. --}}
        <x-emptystate :message="$search !== ''
            ? __('Kein VLAN passt zu „:begriff“.', ['begriff' => $search])
            : __('Noch keine Einträge vorhanden.')" />
@endforelse

    <div class="px-3 pb-3">
        {{ $networks->links() }}
    </div>

</div>
