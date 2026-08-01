<x-app-layout :$customer>

    <x-sitetopmenu can="rack_create" />

    @forelse ($racks as $rack)
        <x-card>
            <x-slot:head>
                <x-show.header can="rack_update" editUrl="{{ route('rack.edit', [$customer, $rack]) }}">
                    {{ $rack->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard title="Allgemein" :array="[
                    'Standort' => $customer->sites->firstWhere('id', $rack->site_id)?->name,
                    'Ort' => $rack->location,
                    'Höheneinheiten' => $rack->height_units . ' HE',
                    'Einbauten' => $rack->items->count(),
                ]" />

                <div class="w-full sm:w-80">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Belegung</div>
                    @include('rack._grid', ['rack' => $rack, 'interactive' => false])
                </div>

                <div class="w-full sm:w-80">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Frontansicht</div>
                    @include('rack._rackview', ['rack' => $rack])
                </div>

            </x-slot>
        </x-card>
    @empty
        <x-emptystate message="Noch keine Serverschränke dokumentiert." />
    @endforelse

    <div class="px-3 pb-3">
        {{ $racks->links() }}
    </div>

</x-app-layout>
