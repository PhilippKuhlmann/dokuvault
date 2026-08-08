<x-app-layout :$customer>
    <x-sitetopmenu can="securepointutm_create" />


    @forelse ($securepointutms as $securepointutm)
        <x-card>
            <x-slot:head>
                <x-show.header can="securepointutm_update" editUrl="{{ route('securepointutm.edit', [$customer, $securepointutm]) }}">
                    {{ $securepointutm->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Art' => $securepointutm->type,
                    'Seriennummer' => $securepointutm->serialNumber,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $securepointutm->username,
                    'Passwort' => $securepointutm->password,
                    'Cloud Backup Passwort' => $securepointutm->cloudBackupPassword,
                    'USC-PIN' => $securepointutm->uscpin,
                ]" />

                <x-minitablecard :title="__('URL')" :array="[
                    'IP' => $securepointutm->ip,
                    'Admin URL' => $securepointutm->urlAdmin,
                    'User URL' => $securepointutm->urlUser,
                    'Externe URL' => $securepointutm->urlExternal,
                ]" />


                <x-credentialscard :device="$securepointutm" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $securepointutms->links() }}
    </div>

</x-app-layout>
