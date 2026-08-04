<x-app-layout :$customer>

    <x-sitetopmenu can="dect_create" />

    @forelse ($dectList as $dect)
        <x-card>
            <x-slot:head>
                <x-show.header can="dect_update" editUrl="{{ route('dect.edit', [$customer, $dect]) }}">
                    Rolle: {{ $dect->role }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $dect->manufacturer,
                    'Modell' => $dect->model,
                    'Seriennummer' => $dect->serialNumber,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'IP-Adresse' => $dect->ip,
                    'Port' => $dect->port,
                    'MAC-Adresse' => $dect->mac,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $dect->username,
                    'Passwort' => $dect->password,
                ]" />




            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $dectList->links() }}
    </div>

</x-app-layout>
