<x-app-layout :$customer>
    <x-create.main :header="__('Backup bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('backup.update', [$customer, $backup]) }}">
        @method('PATCH')
        <x-create.singlerow :label="__('Name')" name="name" :default="$backup->name" />
        <x-create.doublerow :label1="__('Software')" name1="software" :default1="$backup->software" :label2="__('Zeitplan')" name2="schedule" :default2="$backup->schedule" />
        <x-create.doublerow :label1="__('Quelle')" name1="source" :default1="$backup->source" :label2="__('Ziel')" name2="destination" :default2="$backup->destination" />
        <x-create.doublerow :label1="__('Aufbewahrung')" name1="retention" :default1="$backup->retention" :label2="__('Letzter Erfolg')" name2="last_success" :default2="$backup->last_success" type2="date" />
        <x-create.singlerow :label="__('Passwort')" name="password" :default="$backup->password" />
        <x-create.singlerow :label="__('Notizen')" name="notes" :default="$backup->notes" />
    </x-create.main>
    @can('backup_delete')
        <x-deletecard action="{{ route('backup.destroy', [$customer, $backup]) }}" />
    @endcan
</x-app-layout>
