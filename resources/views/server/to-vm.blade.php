<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-4">
        <div>
            <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('In VM umwandeln') }}</div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __(':server ist in Wahrheit eine virtuelle Maschine? Dann wird daraus eine VM – neu angelegt oder mit einer schon dokumentierten VM zusammengeführt.', ['server' => $server->name]) }}
            </div>
        </div>

        <x-panel>
            {{-- What moves, said before anyone clicks - the server goes to
                 the trash afterwards. --}}
            <div class="text-sm text-gray-700 dark:text-gray-200">
                {{ __('Mit wandern: IP-Adressen, verknüpfte Zugangsdaten, AD-Rollen, Netzlaufwerke, Fernwartung und Dienste. Was die VM schon hat, bleibt. Der Server kommt danach in den Papierkorb und lässt sich dort wiederherstellen.') }}
            </div>

            @if ($blocker)
                <div class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">{{ $blocker }}</div>
            @endif
            <x-input.fehler feld="server" />
        </x-panel>

        @unless ($blocker)
            <div class="grid gap-4 lg:grid-cols-2">
                @can('vm_create')
                    <x-panel>
                        <div class="mb-1 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Als neue VM anlegen') }}</div>
                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ __('Name, Standort und Betriebssystem werden übernommen. Host oder Cluster lassen sich gleich mit angeben.') }}</p>
                        <form method="POST" action="{{ route('server.to-vm.store', [$customer, $server]) }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="mode" value="new">
                            <div>
                                <x-input.label for="name" :value="__('Name der VM')" />
                                <x-input.field id="name" name="name" :value="old('name', $server->name)" class="w-full" required />
                                <x-input.fehler feld="name" />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <x-input.label for="server_id" :value="__('Host')" />
                                    <x-input.select id="server_id" name="server_id" class="w-full">
                                        <option value="">{{ __('– ohne –') }}</option>
                                        @foreach ($hosts as $host)
                                            <option value="{{ $host->id }}" @selected((string) old('server_id') === (string) $host->id)>{{ $host->name }}</option>
                                        @endforeach
                                    </x-input.select>
                                    <x-input.fehler feld="server_id" />
                                </div>
                                <div>
                                    <x-input.label for="cluster_id" :value="__('Cluster')" />
                                    <x-input.select id="cluster_id" name="cluster_id" class="w-full">
                                        <option value="">{{ __('– ohne –') }}</option>
                                        @foreach ($clusters as $cluster)
                                            <option value="{{ $cluster->id }}" @selected((string) old('cluster_id') === (string) $cluster->id)>{{ $cluster->name }}</option>
                                        @endforeach
                                    </x-input.select>
                                    <x-input.fehler feld="cluster_id" />
                                </div>
                            </div>
                            <x-input.button type="submit" :label="__('Als neue VM anlegen')" />
                        </form>
                    </x-panel>
                @endcan

                @can('vm_update')
                    <x-panel>
                        <div class="mb-1 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Mit vorhandener VM zusammenführen') }}</div>
                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ __('Für den Fall, dass die Maschine schon als VM dokumentiert ist – etwa vom Proxmox-Agenten unter ihrem Namen dort. Name und Werte der VM bleiben.') }}</p>
                        @if ($vms->isEmpty())
                            <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Bei diesem Kunden ist noch keine VM dokumentiert.') }}</div>
                        @else
                            <form method="POST" action="{{ route('server.to-vm.store', [$customer, $server]) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="mode" value="merge">
                                <div>
                                    <x-input.label for="vm_id" :value="__('VM')" />
                                    <x-input.select id="vm_id" name="vm_id" class="w-full" required>
                                        @foreach ($vms as $vm)
                                            <option value="{{ $vm->id }}" @selected((string) old('vm_id') === (string) $vm->id)>{{ $vm->name }}{{ $vm->host ? ' · '.$vm->host->name : '' }}</option>
                                        @endforeach
                                    </x-input.select>
                                    <x-input.fehler feld="vm_id" />
                                </div>
                                <x-input.button type="submit" :label="__('Zusammenführen')" />
                            </form>
                        @endif
                    </x-panel>
                @endcan
            </div>
        @endunless

        <a href="{{ route('server.index', [$customer, 'highlight' => $server->id]) }}" class="inline-block text-sm text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('Zurück zu den Servern') }}</a>
    </div>
</x-app-layout>
