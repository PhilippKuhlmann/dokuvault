<x-app-layout :$customer>
    <x-create.main :header="__('Login für NAS bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('loginnas.update', [$customer, $loginnas]) }}">
        @method('PATCH')

        <x-edit.select.nas selector="{{ $loginnas->nas?->id }}" :$nas/>

        <x-create.doublerow :label1="__('Benutzer')" name1="username" :default1="$loginnas->username" :label2="__('Passwort')" name2="password" :default2="$loginnas->password" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" :default="$loginnas->description" />

        <x-edit.hidden hidden="{{ $loginnas->hidden }}" />

    </x-create.main>

    @can('loginnas_delete')
        <x-deletecard action="{{ route('loginnas.destroy', [$customer, $loginnas]) }}" />
    @endcan

</x-app-layout>

