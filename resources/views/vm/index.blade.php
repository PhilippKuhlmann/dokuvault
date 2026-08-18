<x-app-layout :$customer>

    <x-sitetopmenu can="vm_create" />

    @forelse ($vms as $vm)
        @php
            $adressen = $vm->relationLoaded('ipAddresses') ? $vm->ipAddresses : $vm->ipAddresses()->get();
            $anzahlIps = $adressen->count();
            $primaer = $adressen->first()?->address;
        @endphp

        <x-card>
            <x-slot:head>
                <x-show.header can="vm_update" editUrl="{{ route('vm.edit', [$customer, $vm]) }}">
                    {{-- Die Fernwartung bleibt der erste Knopf in der Kopfzeile: taeglich
                         benutzt. Welches Werkzeug dahinter steckt, steht in den
                         Einstellungen. --}}
                    <x-remote.button :device="$vm" />
                    {{ $vm->name }}

                    @if ($vm->operatingSystem)
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ $vm->operatingSystem->name }}</span>

                        <x-eol :os="$vm->operatingSystem" />
                    @endif

                    {{-- Wie beim Server: das Nachgeschlagene neben den Namen. Statt des
                         Einbauorts steht hier der Host - eine VM steckt in keinem Rack. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
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

                {{-- Die Fernwartungs-Kennung auch als Text: Wenn der Knopf nicht greift
                     (anderer Rechner, kein Client), braucht man die ID zum Abtippen. --}}
                <x-minitablecard :title="__('Fernwartung')" :array="[
                    \App\Models\Setting::fernwartung()['id_label'] => $vm->remoteID,
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
