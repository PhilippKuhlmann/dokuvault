<x-app-layout :$customer>

    <x-sitetopmenu can="router_create" />


    @forelse ($routers as $router)
        <x-card>
            <x-slot:head>
                <x-show.header can="router_update" editUrl="{{ route('router.edit', [$customer, $router]) }}">
                    {{ $router->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Rack' => $router->einbauort(),
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
                    'IP' => $router->ip,
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
