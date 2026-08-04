<x-app-layout :$customer>
    <x-create.main :header="__('FTP-Server Benutzer bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('ftpserver.update', [$customer, $ftpserver]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Host')" name="host" :default="$ftpserver->host" />

        <x-create.singlerow :label="__('Benutzername')" name="username" :default="$ftpserver->username" />

        <x-create.singlerow :label="__('Passwort')" name="password" :default="$ftpserver->password" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" :default="$ftpserver->description" />

    </x-create.main>

    @can('ftpserver_delete')
        <x-deletecard action="{{ route('ftpserver.destroy', [$customer, $ftpserver]) }}" />
    @endcan

</x-app-layout>
