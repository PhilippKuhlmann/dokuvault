<x-app-layout :$customer>
    <x-create.main :header="__('Neues WLAN')" action="{{ route('wifi.store', $customer) }}">

            <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

            <x-create.singlerow :label="__('SSID')" name="ssid" />

            <x-create.singlerow :label="__('Passwort')" name="password" />

            <x-create.singlerow :label="__('Verschlüsselung')" name="encryption" />

            <x-create.select.network :$networks/>

    </x-create.main>
</x-app-layout>
