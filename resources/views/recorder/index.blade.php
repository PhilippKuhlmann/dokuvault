<x-app-layout :$customer>

    <x-sitetopmenu can="recorder_create" />

    @forelse ($recorders as $recorder)

        @php
            $adressen = $recorder->relationLoaded('ipAddresses') ? $recorder->ipAddresses : $recorder->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="recorder_update" editUrl="{{ route('recorder.edit', [$customer, $recorder]) }}">
                    {{ $recorder->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($recorder->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $recorder->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-ipcard :device="$recorder" />

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $recorder->manufacturer,
                    'Model' => $recorder->model,
                    'Seriennummer' => $recorder->serialNumber,
                ]" />

                <x-credentialscard :device="$recorder" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'Port' => $recorder->port,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $recorder->username,
                    'Passwort' => $recorder->password,
                ]" />

                <x-beschaffungcard :device="$recorder" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $recorders->links() }}
    </div>

</x-app-layout>
