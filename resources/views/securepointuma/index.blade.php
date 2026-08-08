<x-app-layout :$customer>

    <x-sitetopmenu can="securepointuma_create" />

    @forelse ($customer->securepointumas as $securepointuma)
        <x-card>
            <x-slot:head>
                <x-show.header can="securepointuma_update" editUrl="{{ route('securepointuma.edit', [$customer, $securepointuma]) }}">
                    {{ $securepointuma->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Rack' => $securepointuma->einbauort(),
                    'Hersteller / Produkt' => $securepointuma->manufacturer,
                    'Art' => $securepointuma->type,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $securepointuma->username,
                    'Passwort' => $securepointuma->password,
                    'Verschlüsselungscode' => $securepointuma->encryptionkey,
                ]" />

                <x-minitablecard :title="__('URL')" :array="[
                    'IP' => $securepointuma->ip,
                    'Admin URL' => $securepointuma->urlAdmin,
                    'User URL' => $securepointuma->urlUser,
                ]" />


                <x-credentialscard :device="$securepointuma" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

</x-app-layout>
