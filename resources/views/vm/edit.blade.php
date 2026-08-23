<x-app-layout :$customer>
    <x-create.main :header="__('VM bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('vm.update', [$customer, $vm]) }}" breit>
        @method('PATCH')

        {{-- Der Host zuerst: Er beantwortet den Standort gleich mit. Steht
             einer, verschwindet das Standortfeld - der Wert kommt dann in
             VMRequest::prepareForValidation vom Host. --}}
        <x-create.abschnitt :titel="__('Identität')" erste x-data="{ host: '{{ old('server_id', $vm->server_id) }}' }">
            <div class="flex flex-col mt-2">
                <x-input.label for="server_id" :value="__('Host (Server)')" />
                <x-input.select id="server_id" name="server_id" x-model="host">
                    <option value="">{{ __('— kein Host —') }}</option>
                    @foreach ($servers as $server)
                        <option value="{{ $server->id }}" {{ $server->id == $vm->server_id ? 'selected' : '' }}>{{ $server->name }}</option>
                    @endforeach
                </x-input.select>
            </div>

            <div x-show="! host" x-cloak>
                <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $vm->site_id }}" :array="$sites" />
            </div>

            <x-create.singlerow :label="__('Name')" name="name" :default="$vm->name" />

            <x-edit.select.operatingsystem selector="{{ $vm->operatingSystem?->id }}" :$operatingSystems/>
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Fernwartung')">
            <x-create.singlerow :label="\App\Models\Setting::fernwartung()['id_label']" name="remoteID" :default="$vm->remoteID" />

            <x-create.singlerow :label="\App\Models\Setting::fernwartung()['password_label']" name="remotePassword" :default="$vm->remotePassword" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Dienste')" :hinweis="__('Aus dem Katalog wählen oder eigene ergänzen')">
            <x-create.dienste :default="implode(',', $vm->services)" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$vm" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$vm" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('vm_delete')
        <x-deletecard action="{{ route('vm.destroy', [$customer, $vm]) }}" breit />
    @endcan

</x-app-layout>

