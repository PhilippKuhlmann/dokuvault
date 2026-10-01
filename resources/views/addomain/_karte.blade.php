{{-- An AD domain in the list. A card instead of the former table row: a
     domain has more to say than fits in columns, and the blocks follow the
     groups of the form (Domäne, Domänencontroller, Dienste). --}}
<x-card>
    <x-slot:head>
        <x-show.header can="addomain_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'addomain', id: {{ $eintrag->id }} })">
            {{ $eintrag->domain }}

            @if ($eintrag->netbios)
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ $eintrag->netbios }}</span>
            @endif

            {{-- What one looks up first: which level, which machines. --}}
            <x-slot:kernwerte>
                @if ($eintrag->functionalLevelLabel())
                    <x-kernwert :label="__('Ebene')">{{ $eintrag->functionalLevelLabel() }}</x-kernwert>
                @endif

                @if ($eintrag->hostNames('dc'))
                    <x-kernwert :label="__('DC')">{{ $eintrag->hostNames('dc') }}</x-kernwert>
                @endif
            </x-slot>
        </x-show.header>
    </x-slot>

    <x-slot:body>

        <x-minitablecard :title="__('Domäne')" :array="[
            'Domäne' => $eintrag->domain,
            'NetBIOS' => $eintrag->netbios,
            'UPN-Suffixe' => $eintrag->upn_suffixes,
            'Funktionsebene' => $eintrag->functionalLevelLabel(),
        ]" />

        {{-- With the model: the DSRM password is fetched on click, not put
             into the HTML (see x-minitablecard). --}}
        <x-minitablecard :title="__('Domänencontroller')" :modell="$eintrag" :array="[
            'Domänencontroller' => $eintrag->hostNames('dc'),
            'FSMO-Rollen / PDC' => $eintrag->fsmo_holder,
            'DSRM Passwort' => $eintrag->dsrmpassword,
            'DNS-Weiterleitungen' => $eintrag->dns_forwarders,
        ]" />

        <x-minitablecard :title="__('Dienste')" :array="[
            'DHCP-Server' => $eintrag->dhcp_server,
            'Zertifizierungsstelle' => $eintrag->hostNames('ca'),
            'Entra Connect' => $eintrag->entra_connect === null ? null : ($eintrag->entra_connect ? __('Ja') : __('Nein')),
            'Entra-Connect-Server' => $eintrag->hostNames('entra_connect'),
        ]" />

        <x-credentialscard :device="$eintrag" />

        @if ($eintrag->notes)
            {{-- Keep the line breaks of the textarea - otherwise all notes
                 run together into one paragraph. --}}
            <x-minitextcard :title="__('Notizen')"><span class="whitespace-pre-line">{{ $eintrag->notes }}</span></x-minitextcard>
        @endif

    </x-slot>
</x-card>
