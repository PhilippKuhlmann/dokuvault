<x-app-layout :$customer>

    <x-sitetopmenu can="otherclient_create" />


    @forelse ($otherclients as $otherclient)
        <x-card>
            <x-slot:head>
                <x-show.header can="otherclient_update" editUrl="{{ route('otherclient.edit', [$customer, $otherclient]) }}">
                    {{ $otherclient->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $otherclient->manufacturer,
                    'Modell' => $otherclient->model,
                    'Seriennummer' => $otherclient->serialNumber,
                ]" />

                <x-credentialscard :device="$otherclient" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'IP-Adresse' => $otherclient->ip,
                    'Port' => $otherclient->port,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $otherclient->username,
                    'Passwort' => $otherclient->password
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse


    <div class="px-3 pb-3">
        {{ $otherclients->links() }}
    </div>

</x-app-layout>
