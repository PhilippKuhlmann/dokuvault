<x-app-layout :$customer>

    <x-sitetopmenu can="vm_create" />

    @forelse ($vms as $vm)
        @php
            $adressen = $vm->relationLoaded('ipAddresses') ? $vm->ipAddresses : $vm->ipAddresses()->get();
            $anzahlIps = collect([$vm->ip1, $vm->ip2])->filter()->count() + $adressen->count();
        @endphp

        <x-card>
            <x-slot:head>
                <x-show.header can="vm_update" editUrl="{{ route('vm.edit', [$customer, $vm]) }}">
                    {{-- Rustdesk bleibt der erste Knopf in der Kopfzeile: taeglich benutzt. --}}
                    @if ($vm->remoteID AND $vm->remotePassword)
                        <x-input.linkbutton link="rustdesk://connection/new/{{ $vm->remoteID }}?password={{ $vm->remotePassword }}">
                            <x-slot:label>
                                <x-svg.software.rustdesk class="h-6 w-6 !fill-cerulean-500 hover:!fill-cerulean-400" />
                            </x-slot:label>
                        </x-input.linkbutton>
                    @endif
                    {{ $vm->name }}

                    @if ($vm->operatingSystem)
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ $vm->operatingSystem->name }}</span>
                    @endif

                    {{-- Wie beim Server: das Nachgeschlagene neben den Namen. Statt des
                         Einbauorts steht hier der Host - eine VM steckt in keinem Rack. --}}
                    <x-slot:kernwerte>
                        @if ($vm->ip1)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$vm->ip1" />
                            </x-kernwert>
                        @endif

                        @if ($vm->host)
                            <x-kernwert :label="__('Host')">{{ $vm->host->name }}</x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-ipcard :device="$vm" />

                <x-credentialscard :device="$vm" />

                {{-- Die Rustdesk-Kennung auch als Text: Wenn der Knopf nicht greift
                     (anderer Rechner, kein Client), braucht man die ID zum Abtippen. --}}
                <x-minitablecard :title="__('Fernwartung')" :array="[
                    'Rustdesk ID' => $vm->remoteID,
                    'Passwort' => $vm->remotePassword,
                ]" />

                <x-minitagcard :title="__('Dienste')" :array="$vm->services" />

            </x-slot>
        </x-card>
    @empty
        <x-emptystate />
    @endforelse

    <div class="px-3 pb-3">
        {{ $vms->links() }}
    </div>

</x-app-layout>
