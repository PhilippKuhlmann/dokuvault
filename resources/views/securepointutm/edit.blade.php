<x-app-layout :$customer>
    <x-create.main :header="__('Securepoint UTM bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('securepointutm.update', [$customer, $securepointutm]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $securepointutm->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" :default="$securepointutm->name" />

        <x-create.doublerow :label1="__('Type')" name1="type" :default1="$securepointutm->type" :label2="__('Seriennummer')" name2="serialNumber" :default2="$securepointutm->serialNumber" />

        <x-create.singlerow :label="__('Benutzername')" name="username" :default="$securepointutm->username" />

        <x-create.singlerow :label="__('Passwort')" name="password" :default="$securepointutm->password" />

        <x-create.singlerow :label="__('Cloud Backup Passwort')" name="cloudBackupPassword" :default="$securepointutm->cloudBackupPassword" />

        <x-create.singlerow :label="__('USC-PIN')" name="uscpin" :default="$securepointutm->uscpin" />

        <x-create.singlerow :label="__('IP')" name="ip" :default="$securepointutm->ip" />

        <x-create.singlerow :label="__('Admin URL')" name="urlAdmin" :default="$securepointutm->urlAdmin" />

        <x-create.singlerow :label="__('User URL')" name="urlUser" :default="$securepointutm->urlUser" />

        <x-create.singlerow :label="__('Externe URL')" name="urlExternal" :default="$securepointutm->urlExternal" />

    </x-create.main>

    <livewire:device-ip-addresses :model="$securepointutm" :customer="$customer" />

    @can('securepointutm_delete')
        <x-deletecard action="{{ route('securepointutm.destroy', [$customer, $securepointutm]) }}" />
    @endcan

</x-app-layout>
