<x-app-layout :$customer>
    <x-create.main :header="__('WLAN bearbeiten')" :labelsubmit="__('Speichern')"
        action="{{ route('wifi.update', [$customer, $wifi]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $wifi->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('SSID')" name="ssid" :default="$wifi->ssid" />

        <x-create.singlerow :label="__('Passwort')" name="password" :default="$wifi->password" />

        <x-create.singlerow :label="__('Verschlüsselung')" name="encryption" :default="$wifi->encryption" />

        <x-edit.select.network selector="{{ $wifi->network?->id }}" :$networks/>

    </x-create.main>

    <x-deletecard action="{{ route('wifi.destroy', [$customer, $wifi]) }}" />

</x-app-layout>
