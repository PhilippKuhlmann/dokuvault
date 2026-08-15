<x-app-layout :$customer>

    {{-- Statt des eingebauten "Neu" (fuehrt auf eine eigene Seite) dasselbe
         Modal wie im IP-Block am Geraet - man bleibt in der Liste. Der Standort
         wird hier mit abgefragt, anders als am Geraet gibt ihn nichts vor. --}}
    <x-sitetopmenu can="network_create" :neu="false">
        <livewire:network-quick-create :customer="$customer"
            class="inline-flex items-center gap-1.5 rounded-lg bg-cerulean-600 px-4 py-2 text-sm font-DINPro-bold text-white shadow-sm transition-colors hover:bg-cerulean-700" />
    </x-sitetopmenu>


    @forelse ($networks as $network)
        <x-card>
            <x-slot:head>
                <x-show.header can="network_update" editUrl="{{ route('network.edit', [$customer, $network]) }}">
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
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $networks->links() }}
    </div>

</x-app-layout>
