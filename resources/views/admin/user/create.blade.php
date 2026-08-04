<x-admin-layout>
    <x-create.main :header="__('Neuer Benutzer')" action="{{ route('admin.user.store') }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :label2="__('Passwort')" name2="password" />

        <x-create.singlerow :label="__('E-Mail')" name="email" />

        <x-create.select.role :$roles/>

        <x-create.select.customer :$customers/>

    </x-create.main>
</x-admin-layout>
