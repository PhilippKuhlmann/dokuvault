<x-app-layout :$customer>
    <x-create.main header="AD-Benutzer bearbeiten" labelsubmit="Speichern" action="{{ route('aduser.update', [$customer, $aduser]) }}">
        @method('PATCH')

        <x-create.singlerow label="Vorname" name="firstName" :default="$aduser->firstName" />

        <x-create.singlerow label="Nachname" name="lastName" :default="$aduser->lastName" />

        <x-create.singlerow label="Benutzername" name="username" :default="$aduser->username" />

        <x-create.singlerow label="E-Mail" name="email" :default="$aduser->email" />

        <x-create.singlerow label="Passwort" name="password" :default="$aduser->password" />

        <x-edit.radio label="Status" name="enabled" :selector="$aduser->enabled === null ? null : (int) $aduser->enabled" :radios="[
            'Aktiv' => 1,
            'Deaktiviert' => 0,
        ]" />

        <x-edit.hidden hidden="{{ $aduser->hidden }}" />

    </x-create.main>

    @can('aduser_delete')
        <x-deletecard action="{{ route('aduser.destroy', [$customer, $aduser]) }}" />
    @endcan

</x-app-layout>
