<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Server')" action="{{ route('server.store', $customer) }}" breit>

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-create.singlerow :label="__('Name')" name="name" />

            <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

            <x-create.select.operatingsystem :$operatingSystems/>

            {{-- Leerer erster Eintrag: Die allermeisten Server stehen allein,
                 und ein vorbelegter Cluster waere eine falsche Angabe. --}}
            <div class="flex flex-col mt-2">
                <x-input.label for="cluster_id" :value="__('Cluster')" />
                <x-input.select id="cluster_id" name="cluster_id">
                    <option value="">{{ __('— kein Cluster —') }}</option>
                    @foreach ($clusters as $cluster)
                        <option value="{{ $cluster->id }}" @selected(old('cluster_id') == $cluster->id)>{{ $cluster->name }}</option>
                    @endforeach
                </x-input.select>
            </div>
        </x-create.abschnitt>

        {{-- Ein Standserver hat keine Einbautiefe: Das Feld erscheint erst,
             wenn die Bauform 19 Zoll ist. --}}
        <x-create.abschnitt :titel="__('Hardware')" x-data="{ bauform: '{{ old('form_factor', 'rack') }}' }">
            <x-create.singlerow :label="__('Hersteller')" name="manufacturer" />

            <x-create.singlerow :label="__('Model')" name="model" />

            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" />

            <x-create.options :label="__('Bauform')" name="form_factor" :options="config('custom.server_form_factors')" default="rack" x-model="bauform" />

            {{-- Eigenes Raster ueber beide Spalten: x-show schaltet display um, ein
                 durchgereichtes "contents" wuerde mit x-cloak kollidieren. --}}
            <div x-show="bauform === 'rack'" x-cloak class="grid grid-cols-1 gap-x-4 sm:col-span-2 sm:grid-cols-2">
                <x-create.options :label="__('Einbautiefe')" name="full_depth" :options="config('custom.server_depths')" default="1" />

                <x-create.singlerow :label="__('Höheneinheiten (HE)')" name="height_units" type="number" default="1" />
            </div>
        </x-create.abschnitt>

        {{-- IP 1 und IP 2 sind hier raus: Adressen werden ausschliesslich im Block
             "Weitere IP-Adressen" gepflegt, wo Netz und Bezeichnung dranhaengen. --}}
        <x-create.abschnitt :titel="__('Fernwartung')">
            <x-create.singlerow :label="__('BMC IP')" name="bmcIp" class="sm:col-span-2" />

            <x-create.singlerow :label="__('BMC User')" name="bmcUser" />

            <x-create.singlerow :label="__('BMC Passwort')" name="bmcPassword" />

            <x-create.singlerow :label="\App\Models\Setting::fernwartung()['id_label']" name="remoteID" />

            <x-create.singlerow :label="\App\Models\Setting::fernwartung()['password_label']" name="remotePassword" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Dienste')" :hinweis="__('Aus dem Katalog wählen oder eigene ergänzen')">
            <x-create.dienste />
        </x-create.abschnitt>

        {{-- Weitere IP-Adressen und Zugangsdaten haengen am gespeicherten Server;
             beide erscheinen direkt nach dem Anlegen im Bearbeiten-Formular.
             Das steht hier, damit ihr Fehlen nicht wie ein Mangel aussieht. --}}
        <x-create.beschaffung />

        <x-create.abschnitt :titel="__('IP-Adressen und Zugangsdaten')">
            <p class="mt-1 text-sm text-gray-500 sm:col-span-2 dark:text-gray-400">
                {{ __('Lassen sich eintragen, sobald das Gerät angelegt ist.') }}
            </p>
        </x-create.abschnitt>

    </x-create.main>
</x-app-layout>
