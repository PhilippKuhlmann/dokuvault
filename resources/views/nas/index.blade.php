<x-app-layout :$customer>

    <x-sitetopmenu can="nas_create" />


    @forelse ($nasList as $nas)
        <x-card>
            <x-slot:head>
                <x-show.header can="nas_update" editUrl="{{ route('nas.edit', [$customer, $nas]) }}" >
                    {{ $nas->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Hardware')" :array="[
                    'Rack' => $nas->einbauort(),
                    'Hersteller' => $nas->manufacturer,
                    'Modell' => $nas->model,
                    'Seriennummer' => $nas->serialNumber,
                ]" />

                <x-credentialscard :device="$nas" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'IP-Adresse 1' => $nas->ip1,
                    'IP-Adresse 2' => $nas->ip2,
                    'Port' => $nas->port,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $nas->username,
                    'Passwort' => $nas->password,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">
        {{ $nasList->links() }}
    </div>

</x-app-layout>
