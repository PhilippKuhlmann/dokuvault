<x-app-layout :$customer>
    <x-sitetopmenu can="camera_create" />

    @forelse ($cameras as $camera)

        @php
            $adressen = $camera->relationLoaded('ipAddresses') ? $camera->ipAddresses : $camera->ipAddresses()->get();
            $primaer = $camera->ip1 ?? $camera->ip;
            $anzahlIps = collect([$primaer, $camera->ip2 ?? null])->filter()->count() + $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="camera_update" editUrl="{{ route('camera.edit', [$customer, $camera]) }}">
                    {{ $camera->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$camera" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $camera->manufacturer,
                    'Model' => $camera->model,
                    'Seriennummer' => $camera->serialNumber,
                ]" />

                <x-credentialscard :device="$camera" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $camera->port,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $camera->username,
                    'Passwort' => $camera->password,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $cameras->links() }}
    </div>

</x-app-layout>
