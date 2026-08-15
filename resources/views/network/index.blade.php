<x-app-layout :$customer>

    <x-sitetopmenu can="network_create" />

    {{-- Dasselbe Modal wie im IP-Block am Geraet: ein VLAN anlegen, ohne die
         Liste zu verlassen. Der Standort wird hier mit abgefragt - anders als
         am Geraet gibt ihn nichts vor. --}}
    <div class="px-3 pb-1">
        <livewire:network-quick-create :customer="$customer" />
    </div>


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
