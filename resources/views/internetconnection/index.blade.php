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
                'Download' => $ic->bandbreite($ic->bandwidth_down),
                'Upload' => $ic->bandbreite($ic->bandwidth_up),
                'WAN-IP' => $ic->wan_ip,
                'Hotline' => $ic->hotline,
            ]" />

            {{-- Einwahldaten nur, wenn gepflegt: Nicht jeder Anschluss braucht
                 PPPoE, und eine leere Karte waere nur Rauschen. --}}
            @if ($ic->pppoe_user || $ic->pppoe_password)
                <x-minitablecard :title="__('Einwahl (PPPoE)')" :array="[
                    'Benutzer' => $ic->pppoe_user,
                    'Passwort' => $ic->pppoe_password,
                ]" />
            @endif

            {{-- Nur wenn ein geroutetes Netz hinterlegt ist - die meisten
                 Anschluesse haben nur die eine WAN-Adresse. --}}
            @if ($ic->subnet)
                <x-minitablecard :title="__('Geroutetes Netz')" :array="[
                    'Netz' => $ic->subnet,
                    'Gateway' => $ic->subnet_gateway,
                    'Nutzbar' => $ic->nutzbarerBereich(),
                ]" />
            @endif
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
