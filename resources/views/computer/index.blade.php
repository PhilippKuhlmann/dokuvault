<x-app-layout :$customer>

    <x-sitetopmenu can="computer_create" />


    @forelse ($computers as $computer)
    <x-card>
        <x-slot:head>
            <x-show.header can="computer_update" editUrl="{{ route('computer.edit', [$customer, $computer]) }}">
                @if ($computer->remoteID AND $computer->remotePassword)
                    <a href="rustdesk://connection/new/{{ $computer->remoteID }}?password={{ $computer->remotePassword }}" class="bg-cerulean-600 text-white rounded-lg px-4 py-2 text-sm mr-5 hover:bg-cerulean-700">{{ __('Verbinden') }}</a>
                @endif
                {{ $computer->name }}
            </x-show.header>
        </x-slot>

        <x-slot:body>

            <x-minitablecard :title="__('Allgemein')" :array="[
                'Hersteller' => $computer->manufacturer,
                'Modell' => $computer->model,
                'Seriennummer' => $computer->serialNumber,
            ]" />

            <x-minitablecard :title="__('Netzwerk')" :array="[
                'IP-Adresse' => $computer->ip,
            ]" />

            <x-minitextcard :title="__('Betriebsystem')">
                {{ $computer->operatingSystem?->name ?? '—' }}
            </x-minitextcard>

        </x-slot>
    </x-card>
@empty
    <x-emptystate />
@endforelse


    <div class="px-3 pb-3">
        {{ $computers->links() }}
    </div>

</x-app-layout>
