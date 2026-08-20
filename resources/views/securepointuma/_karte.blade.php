{{-- Eine E-Mail-Archivierung in der Liste. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="securepointuma_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'securepointuma', id: {{ $eintrag->id }} })">
                    {{ $eintrag->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($eintrag->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $eintrag->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$eintrag" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller / Produkt' => $eintrag->manufacturer,
                    'Art' => $eintrag->type,
                ]" />

                <x-credentialscard :device="$eintrag" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $eintrag->username,
                    'Passwort' => $eintrag->password,
                    'Verschlüsselungscode' => $eintrag->encryptionkey,
                ]" />

                <x-minitablecard :title="__('URL')" :array="[
                    'Admin URL' => $eintrag->urlAdmin,
                    'User URL' => $eintrag->urlUser,
                ]" />

            </x-slot>
        </x-card>
    
