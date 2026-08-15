<x-app-layout :$customer>
    <x-create.main :header="__('Securepoint UTM bearbeiten')" :labelsubmit="__('Stammdaten speichern')" action="{{ route('securepointutm.update', [$customer, $securepointutm]) }}" breit>
        @method('PATCH')

        <x-create.abschnitt :titel="__('Identität')" erste>
            <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $securepointutm->site_id }}" :array="$sites" />

            <x-create.singlerow :label="__('Name')" name="name" :default="$securepointutm->name" />

            <x-create.singlerow :label="__('Type')" name="type" :default="$securepointutm->type" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Hardware')">
            <x-create.singlerow :label="__('Seriennummer')" name="serialNumber" :default="$securepointutm->serialNumber" />
        </x-create.abschnitt>

        <x-create.abschnitt :titel="__('Zugang')">
            <x-create.singlerow :label="__('Benutzername')" name="username" :default="$securepointutm->username" />

            <x-create.singlerow :label="__('Passwort')" name="password" :default="$securepointutm->password" />

            <x-create.singlerow :label="__('Cloud Backup Passwort')" name="cloudBackupPassword" :default="$securepointutm->cloudBackupPassword" />

            <x-create.singlerow :label="__('USC-PIN')" name="uscpin" :default="$securepointutm->uscpin" />

            <x-create.singlerow :label="__('Admin URL')" name="urlAdmin" :default="$securepointutm->urlAdmin" />

            <x-create.singlerow :label="__('User URL')" name="urlUser" :default="$securepointutm->urlUser" />

            <x-create.singlerow :label="__('Externe URL')" name="urlExternal" :default="$securepointutm->urlExternal" />
        </x-create.abschnitt>

        {{-- In derselben Karte, aber ausserhalb des <form>: HTML erlaubt keine
             verschachtelten Formulare, und beide Bloecke sind eigenstaendige
             Livewire-Komponenten. --}}
        <x-slot:nach>
            <livewire:device-ip-addresses :model="$securepointutm" :customer="$customer" eingebettet />
            <livewire:device-credentials :model="$securepointutm" :customer="$customer" eingebettet />
        </x-slot>

    </x-create.main>

    @can('securepointutm_delete')
        <x-deletecard action="{{ route('securepointutm.destroy', [$customer, $securepointutm]) }}" breit />
    @endcan

</x-app-layout>
