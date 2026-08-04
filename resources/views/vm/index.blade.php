<x-app-layout :$customer>

    <x-sitetopmenu can="vm_create" />

    @forelse ($vms as $vm)
        <x-card>
            <x-slot:head>
                <x-show.header can="vm_update" editUrl="{{ route('vm.edit', [$customer, $vm]) }}">
                    @if ($vm->remoteID AND $vm->remotePassword)
                        <x-input.linkbutton link="rustdesk://connection/new/{{ $vm->remoteID }}?password={{ $vm->remotePassword }}">
                            <x-slot:label>
                                <x-svg.software.rustdesk class="h-6 w-6 !fill-cerulean-500 hover:!fill-cerulean-400" />
                            </x-slot:label>
                        </x-input.linkbutton>
                    @endif
                    {{ $vm->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Host' => $vm->host?->name,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'IP-Adresse 1' => $vm->ip1,
                    'IP-Adresse 2' => $vm->ip2,
                ]" />

                <x-minitagcard :title="__('Dienste')" :array="$vm->services" />

                <x-minitextcard :title="__('Betriebsystem')">
                    {{ $vm->operatingSystem?->name ?? '—' }}
                </x-minitextcard>

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">
        {{ $vms->links() }}
    </div>

</x-app-layout>
