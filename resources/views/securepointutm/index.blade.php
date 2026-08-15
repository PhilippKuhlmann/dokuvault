<x-app-layout :$customer>
    <x-sitetopmenu can="securepointutm_create" />


    @forelse ($securepointutms as $securepointutm)

        @php
            $adressen = $securepointutm->relationLoaded('ipAddresses') ? $securepointutm->ipAddresses : $securepointutm->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="securepointutm_update" editUrl="{{ route('securepointutm.edit', [$customer, $securepointutm]) }}">
                    {{ $securepointutm->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$securepointutm" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Art' => $securepointutm->type,
                    'Seriennummer' => $securepointutm->serialNumber,
                ]" />

                <x-credentialscard :device="$securepointutm" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $securepointutm->username,
                    'Passwort' => $securepointutm->password,
                    'Cloud Backup Passwort' => $securepointutm->cloudBackupPassword,
                    'USC-PIN' => $securepointutm->uscpin,
                ]" />

                <x-minitablecard :title="__('URL')" :array="[
                    'Admin URL' => $securepointutm->urlAdmin,
                    'User URL' => $securepointutm->urlUser,
                    'Externe URL' => $securepointutm->urlExternal,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $securepointutms->links() }}
    </div>

</x-app-layout>
