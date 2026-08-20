{{-- Ein Internetanschluss in der Liste. --}}
    <x-card>
        <x-slot:head>
            <x-show.header can="internetconnection_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'internetconnection', id: {{ $eintrag->id }} })">
                {{ $eintrag->provider }} {{ $eintrag->product ? '– '.$eintrag->product : '' }}
            </x-show.header>
        </x-slot>
        <x-slot:body>
            <x-minitablecard :title="__('Vertrag')" :array="[
                'Anbieter' => $eintrag->provider,
                'Produkt' => $eintrag->product,
                'Vertragsnummer' => $eintrag->contract_number,
                'Anschlussart' => $eintrag->connection_type,
            ]" />
            <x-minitablecard :title="__('Technik')" :array="[
                'Download' => $eintrag->bandbreite($eintrag->bandwidth_down),
                'Upload' => $eintrag->bandbreite($eintrag->bandwidth_up),
                'WAN-IP' => $eintrag->wan_ip,
                'Hotline' => $eintrag->hotline,
            ]" />

            {{-- Einwahldaten nur, wenn gepflegt: Nicht jeder Anschluss braucht
                 PPPoE, und eine leere Karte waere nur Rauschen. --}}
            @if ($eintrag->pppoe_user || $eintrag->pppoe_password)
                <x-minitablecard :title="__('Einwahl (PPPoE)')" :array="[
                    'Benutzer' => $eintrag->pppoe_user,
                    'Passwort' => $eintrag->pppoe_password,
                ]" />
            @endif

            {{-- Nur wenn ein geroutetes Netz hinterlegt ist - die meisten
                 Anschluesse haben nur die eine WAN-Adresse. --}}
            @if ($eintrag->subnet)
                <x-minitablecard :title="__('Geroutetes Netz')" :array="[
                    'Netz' => $eintrag->subnet,
                    'Gateway' => $eintrag->subnet_gateway,
                    'Nutzbar' => $eintrag->nutzbarerBereich(),
                ]" />
            @endif
            @if ($eintrag->notes)
                <x-minitextcard :title="__('Notizen')">{{ $eintrag->notes }}</x-minitextcard>
            @endif
        </x-slot>
    </x-card>
    
