<x-app-layout :$customer>

    <x-sitetopmenu can="router_create" />


    @forelse ($routers as $router)

        @php
            $adressen = $router->relationLoaded('ipAddresses') ? $router->ipAddresses : $router->ipAddresses()->get();
            $primaer = $router->ip1 ?? $router->ip;
            $anzahlIps = collect([$primaer, $router->ip2 ?? null])->filter()->count() + $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="router_update" editUrl="{{ route('router.edit', [$customer, $router]) }}">
                    {{ $router->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($router->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $router->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$router" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $router->manufacturer,
                    'Modell' => $router->model,
                    'Seriennummer' => $router->serialNumber,
                ]" />

                <x-credentialscard :device="$router" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $router->username,
                    'Passwort' => $router->password,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $router->port,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $routers->links() }}
    </div>

</x-app-layout>
