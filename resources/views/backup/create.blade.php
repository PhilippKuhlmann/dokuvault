<x-app-layout :$customer>
    <x-create.main :header="__('Neues Backup')" action="{{ route('backup.store', $customer) }}">
        <x-create.singlerow :label="__('Name')" name="name" />
        <x-create.doublerow :label1="__('Software')" name1="software" :label2="__('Zeitplan')" name2="schedule" />
        <x-create.doublerow :label1="__('Quelle')" name1="source" :label2="__('Ziel')" name2="destination" />
        <x-create.doublerow :label1="__('Aufbewahrung')" name1="retention" :label2="__('Letzter Erfolg')" name2="last_success" type2="date" />
        <x-create.singlerow :label="__('Passwort')" name="password" />
        <x-create.singlerow :label="__('Notizen')" name="notes" />
    </x-create.main>
</x-app-layout>
