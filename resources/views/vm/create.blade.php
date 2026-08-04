<x-app-layout :$customer>
    <x-create.main :header="__('Neue VM')" action="{{ route('vm.store', $customer) }}">

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

        <x-create.doublerow :label1="__('IP 1')" name1="ip1" :label2="__('IP 2')" name2="ip2" />

        <x-create.doublerow :label1="__('Rustdesk ID')" name1="remoteID" :label2="__('Rustdesk Passwort')" name2="remotePassword" />

        <x-create.select.operatingsystem :$operatingSystems/>

        <x-create.singlerow :label="__('Dienste Bitte mit komma getrennt angeben (eins,zwei,drei)')" name="services" />

    </x-create.main>
</x-app-layout>
