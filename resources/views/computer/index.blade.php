<x-app-layout :$customer>

    <x-sitetopmenu can="computer_create" />


    @forelse ($computers as $computer)

        @php
            $adressen = $computer->relationLoaded('ipAddresses') ? $computer->ipAddresses : $computer->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
    <x-card>
        <x-slot:head>
            <x-show.header can="computer_update" editUrl="{{ route('computer.edit', [$customer, $computer]) }}">
                @if ($computer->remoteID AND $computer->remotePassword)
                    <x-remote.button :device="$computer" stil="text" />
                @endif
                {{ $computer->name }}

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


            <x-ipcard :device="$computer" />

            <x-minitablecard :title="__('Allgemein')" :array="[
                'Hersteller' => $computer->manufacturer,
                'Modell' => $computer->model,
                'Seriennummer' => $computer->serialNumber,
            ]" />

            <x-credentialscard :device="$computer" />

            <x-minitextcard :title="__('Betriebssystem')">
                {{ $computer->operatingSystem?->name ?? '—' }}
                <x-eol :os="$computer->operatingSystem" />
            </x-minitextcard>

            <x-beschaffungcard :device="$computer" />

        </x-slot>
    </x-card>
@empty
    <x-emptystate />
@endforelse


    <div class="px-3 pb-3">
        {{ $computers->links() }}
    </div>

</x-app-layout>
