<x-app-layout :$customer>
    <x-create.main :header="__('Neue Software Lizenz')" action="{{ route('licensesoftware.store', $customer) }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('Key')" name="key" />

        <x-create.singlerow :label="__('Benutzername')" name="username" />

        <x-create.singlerow :label="__('Passwort')" name="password" />

        <x-create.doublerow type1="date" :label1="__('Start Datum')" name1="start_date" type2="date" :label2="__('End Datum')" name2="end_date" />

        <x-create.radio :label="__('Abonnement')" name="abo" :radios="[
            'Kein Abo' => null,
            'Jährlich' => 'Jährlich',
            'Monatlich' => 'Monatlich',
        ]" />

        <x-input.label :value="__('Datei')" class="mt-2" />
        <x-input.file name="file" />

        <x-create.singlerow :label="__('Datei Name')" name="file_name" />

    </x-create.main>
</x-app-layout>
