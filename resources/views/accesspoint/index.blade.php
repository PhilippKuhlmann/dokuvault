<x-app-layout :$customer>

    <x-sitetopmenu can="accesspoint_create" />


    @forelse ($accesspoints as $accesspoint)
        <x-card>
            <x-slot:head>
                <x-show.header can="accesspoint_update" editUrl="{{ route('accesspoint.edit', [$customer, $accesspoint]) }}">
                    {{ $accesspoint->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $accesspoint->manufacturer,
                    'Modell' => $accesspoint->model,
                    'Seriennummer' => $accesspoint->serialNumber,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $accesspoint->username,
                    'Passwort' => $accesspoint->password,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'IP' => $accesspoint->ip,
                    'Port' => $accesspoint->port,
                ]" />


                <x-credentialscard :device="$accesspoint" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $accesspoints->links() }}
    </div>

</x-app-layout>
