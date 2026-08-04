<x-app-layout :$customer>

    <x-sitetopmenu can="phonesystem_create" />

    @forelse ($phoneSystems as $phoneSystem)
        <x-card>
            <x-slot:head>
                <x-show.header can="phonesystem_update" editUrl="{{ route('phonesystem.edit', [$customer, $phoneSystem]) }}">
                    {{ $phoneSystem->manufacturer }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Modell' => $phoneSystem->model,
                    'Seriennummer' => $phoneSystem->serialNumber,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'IP-Adresse 1' => $phoneSystem->ip1,
                    'Port' => $phoneSystem->port,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $phoneSystem->username,
                    'Passwort' => $phoneSystem->password,
                ]" />




            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $phoneSystems->links() }}
    </div>

</x-app-layout>
