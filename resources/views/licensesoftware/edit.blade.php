<x-app-layout :$customer>
    <x-create.main :header="__('Software Lizenz bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('licensesoftware.update', [$customer, $licensesoftware]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$licensesoftware->name" />

        <x-create.singlerow :label="__('Key')" name="key" :default="$licensesoftware->key" />

        <x-create.singlerow :label="__('Benutzername')" name="username" :default="$licensesoftware->username" />

        <x-create.singlerow :label="__('Passwort')" name="password" :default="$licensesoftware->password" />

        <x-create.doublerow
            type1="date" :label1="__('Start Datum')" name1="start_date" :default1="$licensesoftware->start_date"
            type2="date" :label2="__('End Datum')" name2="end_date" :default2="$licensesoftware->end_date"
        />

        <x-edit.radio :label="__('Abonnement')" name="abo" selector="{{ $licensesoftware->abo }}" :radios="[
            'Kein Abo' => null,
            'Jährlich' => 'Jährlich',
            'Monatlich' => 'Monatlich',
        ]" />

        <x-input.label :value="__('Datei')" class="mt-2" />
        <x-input.file name="file" />

        <x-create.singlerow :label="__('Datei Name')" name="file_name" />

    </x-create.main>

    @can('licensesoftware_delete')
        <x-deletecard action="{{ route('licensesoftware.destroy', [$customer, $licensesoftware]) }}" />
    @endcan

</x-app-layout>
