<x-app-layout :$customer>

    <x-sitetopmenu can="phonesystem_create" />

    @forelse ($phoneSystems as $phoneSystem)

        @php
            $adressen = $phoneSystem->relationLoaded('ipAddresses') ? $phoneSystem->ipAddresses : $phoneSystem->ipAddresses()->get();
            $primaer = $phoneSystem->ip1 ?? $phoneSystem->ip;
            $anzahlIps = collect([$primaer, $phoneSystem->ip2 ?? null])->filter()->count() + $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="phonesystem_update" editUrl="{{ route('phonesystem.edit', [$customer, $phoneSystem]) }}">
                    {{ $phoneSystem->manufacturer }}

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


                <x-ipcard :device="$phoneSystem" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Modell' => $phoneSystem->model,
                    'Seriennummer' => $phoneSystem->serialNumber,
                ]" />

                <x-credentialscard :device="$phoneSystem" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
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
