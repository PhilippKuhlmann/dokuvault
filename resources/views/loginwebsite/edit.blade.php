<x-app-layout :$customer>
    <x-create.main :header="__('Webseite bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('loginwebsite.update', [$customer, $loginwebsite]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$loginwebsite->name" />

        <x-create.singlerow :label="__('URL')" name="url" :default="$loginwebsite->url" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :default1="$loginwebsite->username" :label2="__('Passwort')" name2="password" :default2="$loginwebsite->password" />

        <x-edit.hidden hidden="{{ $loginwebsite->hidden }}" />

    </x-create.main>

    @can('loginwebsite_delete')
        <x-deletecard action="{{ route('loginwebsite.destroy', [$customer, $loginwebsite]) }}" />
    @endcan

</x-app-layout>
