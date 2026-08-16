<x-app-layout :$customer>
    <x-create.main :header="__('Ansprechpartner bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('contactperson.update', [$customer, $contactperson]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Vorname')" name="first_name" :default="$contactperson->first_name" />

        <x-create.singlerow :label="__('Nachname')" name="last_name" :default="$contactperson->last_name" />

        <x-create.singlerow :label="__('Funktion')" name="role" :default="$contactperson->role" />

        <x-create.singlerow :label="__('Tel.')" name="phone" :default="$contactperson->phone" />

        <x-create.singlerow :label="__('E-Mail')" name="mail" :default="$contactperson->mail" />

    </x-create.main>

    @can('contactperson_delete')
        <x-deletecard action="{{ route('contactperson.destroy', [$customer, $contactperson]) }}" />
    @endcan

</x-app-layout>
