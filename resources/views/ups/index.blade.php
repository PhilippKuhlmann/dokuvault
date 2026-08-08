<x-app-layout :$customer>
    <x-sitetopmenu can="ups_create" />
    @forelse ($ups as $usv)
    <x-card>
        <x-slot:head>
            <x-show.header can="ups_update" editUrl="{{ route('ups.edit', [$customer, $usv]) }}">
                {{ $usv->name }}
            </x-show.header>
        </x-slot>
        <x-slot:body>
            <x-minitablecard :title="__('Allgemein')" :array="[
                    'Rack' => $usv->einbauort(),
                'Hersteller' => $usv->manufacturer,
                'Modell' => $usv->model,
                'Seriennummer' => $usv->serialNumber,
            ]" />

            <x-credentialscard :device="$usv" />
            <x-minitablecard :title="__('Technik')" :array="[
                'IP-Adresse' => $usv->ip,
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
