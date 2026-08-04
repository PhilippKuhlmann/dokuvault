<x-app-layout :$customer>
    <x-create.main :header="__('VM bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('vm.update', [$customer, $vm]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" value="Standort" selector="{{ $vm->site_id }}" :array="$sites" />

        <div class="flex flex-col mt-2">
            <x-input.label for="server_id" :value="__('Host (Server)')" />
            <x-input.select id="server_id" name="server_id">
                <option value="">— kein Host —</option>
                @foreach ($servers as $server)
                    <option value="{{ $server->id }}" {{ $server->id == $vm->server_id ? 'selected' : '' }}>{{ $server->name }}</option>
                @endforeach
            </x-input.select>
        </div>

        <x-create.singlerow :label="__('Name')" name="name" :default="$vm->name" />

        <x-create.doublerow :label1="__('IP 1')" name1="ip1" :default1="$vm->ip1" :label2="__('IP 2')" name2="ip2" :default2="$vm->ip2" />

        <x-create.doublerow :label1="__('Rustdesk ID')" name1="remoteID" :default1="$vm->remoteID" :label2="__('Rustdesk Passwort')" name2="remotePassword" :default2="$vm->remotePassword" />

        <x-edit.select.operatingsystem selector="{{ $vm->operatingSystem?->id }}" :$operatingSystems/>

        <x-create.singlerow :label="__('Dienste Bitte mit komma getrennt angeben (eins,zwei,drei)')" name="services" :default="implode(',', $vm->services)" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$vm" :customer="$customer" />

    @can('vm_delete')
        <x-deletecard action="{{ route('vm.destroy', [$customer, $vm]) }}" />
    @endcan


</x-app-layout>

