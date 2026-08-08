<x-app-layout :$customer>

    <x-sitetopmenu can="networkswitch_create" />


    @forelse ($networkswitches as $networkswitch)
        <x-card>
            <x-slot:head>
                <x-show.header can="networkswitch_update" editUrl="{{ route('networkswitch.edit', [$customer, $networkswitch]) }}">
                    {{ $networkswitch->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Rack' => $networkswitch->einbauort(),
                    'Hersteller' => $networkswitch->manufacturer,
                    'Modell' => $networkswitch->model,
                    'Seriennummer' => $networkswitch->serialNumber,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzername' => $networkswitch->username,
                    'Passwort' => $networkswitch->password,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'IP' => $networkswitch->ip,
                    'Port' => $networkswitch->port,
                ]" />


                <x-credentialscard :device="$networkswitch" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $networkswitches->links() }}
    </div>

</x-app-layout>
