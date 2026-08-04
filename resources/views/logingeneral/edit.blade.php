<x-app-layout :$customer>
    <x-create.main :header="__('Login bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('logingeneral.update', [$customer, $logingeneral]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$logingeneral->name" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" :default="$logingeneral->description" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :default1="$logingeneral->username" :label2="__('Passwort')" name2="password" :default2="$logingeneral->password" />

        <x-edit.hidden hidden="{{ $logingeneral->hidden }}" />

    </x-create.main>

    @can('logingeneral_delete')
        <x-deletecard action="{{ route('logingeneral.destroy', [$customer, $logingeneral]) }}" />
    @endcan

</x-app-layout>
