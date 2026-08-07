<x-app-layout :$customer>
    <x-create.main :header="__('Neuer Internet-Anschluss')" action="{{ route('internetconnection.store', $customer) }}">
        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />
        <x-create.doublerow :label1="__('Anbieter')" name1="provider" :label2="__('Produkt')" name2="product" />
        <x-create.doublerow :label1="__('Vertragsnummer')" name1="contract_number" :label2="__('Anschlussart')" name2="connection_type" />
        <x-create.doublerow :label1="__('Download')" name1="bandwidth_down" :label2="__('Upload')" name2="bandwidth_up" />
        <x-create.doublerow :label1="__('WAN-IP')" name1="wan_ip" :label2="__('Hotline')" name2="hotline" />
        <x-create.doublerow :label1="__('Netz (optional, z. B. 203.0.113.16/28)')" name1="subnet"
            :label2="__('Gateway des Netzes')" name2="subnet_gateway" />
        <x-create.singlerow :label="__('Notizen')" name="notes" />
    </x-create.main>
</x-app-layout>
