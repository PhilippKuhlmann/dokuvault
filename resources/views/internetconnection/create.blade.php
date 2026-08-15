<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Internet-Anschluss')" action="{{ route('internetconnection.store', $customer) }}">
        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />
        <x-create.doublerow :label1="__('Anbieter')" name1="provider" :label2="__('Produkt')" name2="product" />
        <x-create.doublerow :label1="__('Vertragsnummer')" name1="contract_number" :label2="__('Anschlussart')" name2="connection_type" />
        <div class="flex flex-col sm:flex-row gap-2">
            <x-create.einheit :label="__('Download')" name="bandwidth_down" einheit="Mbit/s" />
            <x-create.einheit :label="__('Upload')" name="bandwidth_up" einheit="Mbit/s" />
        </div>
        <x-create.doublerow :label1="__('WAN-IP')" name1="wan_ip" :label2="__('Hotline')" name2="hotline" />
        <x-create.doublerow :label1="__('Netz (optional, z. B. 203.0.113.16/28)')" name1="subnet"
            :label2="__('Gateway des Netzes')" name2="subnet_gateway" />
        <x-create.doublerow :label1="__('Einwahl-Benutzer (PPPoE)')" name1="pppoe_user" :label2="__('Einwahl-Passwort')" name2="pppoe_password" />
        <x-create.singlerow :label="__('Notizen')" name="notes" />
    </x-create.main>
</x-app-layout>
