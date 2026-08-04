<x-app-layout :$customer>
    <x-create.main :header="__('Neue Securepoint UTM')" action="{{ route('securepointutm.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.doublerow :label1="__('Type')" name1="type" :label2="__('Seriennummer')" name2="serialNumber" />

        <x-create.singlerow :label="__('Benutzername')" name="username" />

        <x-create.singlerow :label="__('Passwort')" name="password" />

        <x-create.singlerow :label="__('Cloud Backup Passwort')" name="cloudBackupPassword" />

        <x-create.singlerow :label="__('USC-PIN')" name="uscpin" />

        <x-create.singlerow :label="__('IP')" name="ip" />

        <x-create.singlerow :label="__('Admin URL')" name="urlAdmin" />

        <x-create.singlerow :label="__('User URL')" name="urlUser" />

        <x-create.singlerow :label="__('Externe URL')" name="urlExternal" />

    </x-create.main>
</x-app-layout>
