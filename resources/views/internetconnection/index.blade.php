<x-app-layout :$customer>
    <x-sitetopmenu can="internetconnection_create" />
    @forelse ($internetconnections as $ic)
    <x-card>
        <x-slot:head>
            <x-show.header can="internetconnection_update" editUrl="{{ route('internetconnection.edit', [$customer, $ic]) }}">
                {{ $ic->provider }} {{ $ic->product ? '– '.$ic->product : '' }}
            </x-show.header>
        </x-slot>
        <x-slot:body>
            <x-minitablecard :title="__('Vertrag')" :array="[
                'Anbieter' => $ic->provider,
                'Produkt' => $ic->product,
                'Vertragsnummer' => $ic->contract_number,
                'Anschlussart' => $ic->connection_type,
            ]" />
            <x-minitablecard :title="__('Technik')" :array="[
                'Download' => $ic->bandwidth_down,
                'Upload' => $ic->bandwidth_up,
                'WAN-IP' => $ic->wan_ip,
                'Hotline' => $ic->hotline,
            ]" />
            @if ($ic->notes)
                <x-minitextcard :title="__('Notizen')">{{ $ic->notes }}</x-minitextcard>
            @endif
        </x-slot>
    </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">{{ $internetconnections->links() }}</div>
</x-app-layout>
