<x-app-layout :$customer>
    <x-sitetopmenu can="ups_create" />
    @forelse ($ups as $usv)

        @php
            $adressen = $usv->relationLoaded('ipAddresses') ? $usv->ipAddresses : $usv->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
    <x-card>
        <x-slot:head>
            <x-show.header can="ups_update" editUrl="{{ route('ups.edit', [$customer, $usv]) }}">
                {{ $usv->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif

                        @if ($usv->einbauort())
                            <x-kernwert :label="__('Rack')">{{ $usv->einbauort() }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
        </x-slot>
        <x-slot:body>

            <x-ipcard :device="$usv" />
            <x-minitablecard :title="__('Allgemein')" :array="[
                'Hersteller' => $usv->manufacturer,
                'Modell' => $usv->model,
                'Seriennummer' => $usv->serialNumber,
            ]" />

            <x-credentialscard :device="$usv" />
            <x-minitablecard :title="__('Technik')" :array="[
                'Kapazität' => $usv->capacity,
                'Laufzeit' => $usv->runtime,
            ]" />
            @if ($usv->notes)
                <x-minitextcard :title="__('Notizen')">{{ $usv->notes }}</x-minitextcard>
            @endif

        </x-slot>
    </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">{{ $ups->links() }}</div>
</x-app-layout>
