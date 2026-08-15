<x-app-layout :$customer>
    <x-create.main :header="__('Neue VM')" action="{{ route('vm.store', $customer) }}" breit>
        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

            <div class="flex flex-col mt-2">
                <x-input.label for="server_id" :value="__('Host (Server)')" />
                <x-input.select id="server_id" name="server_id">
                    <option value="">— kein Host —</option>
                    @foreach ($servers as $server)
                        <option value="{{ $server->id }}" {{ $server->id == old('server_id') ? 'selected' : '' }}>{{ $server->name }}</option>
                    @endforeach
                </x-input.select>
            </div>

            <x-create.singlerow :label="__('Name')" name="name" />

            <x-create.select.operatingsystem :$operatingSystems/>
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Fernwartung')">
            <x-create.singlerow :label="__('Rustdesk ID')" name="remoteID" />

            <x-create.singlerow :label="__('Rustdesk Passwort')" name="remotePassword" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Dienste')" :hinweis="__('Aus dem Katalog wählen oder eigene ergänzen')">
            <x-create.dienste />
        </x-create.abschnitt>

        {{-- Weitere IP-Adressen und Zugangsdaten haengen am gespeicherten Objekt;
             beide erscheinen direkt nach dem Anlegen im Bearbeiten-Formular.
             Das steht hier, damit ihr Fehlen nicht wie ein Mangel aussieht. --}}
        <x-create.abschnitt :titel="__('IP-Adressen und Zugangsdaten')">
            <p class="mt-1 text-sm text-gray-500 sm:col-span-2 dark:text-gray-400">
                {{ __('Lassen sich eintragen, sobald das Gerät angelegt ist.') }}
            </p>
        </x-create.abschnitt>

    </x-create.main>
</x-app-layout>
