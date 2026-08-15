<x-app-layout :$customer>
    <x-create.main :header="__('Internet-Anschluss bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('internetconnection.update', [$customer, $internetconnection]) }}">
        @method('PATCH')
        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $internetconnection->site_id }}" :array="$sites" />
        <x-create.doublerow :label1="__('Anbieter')" name1="provider" :default1="$internetconnection->provider" :label2="__('Produkt')" name2="product" :default2="$internetconnection->product" />
        <x-create.doublerow :label1="__('Vertragsnummer')" name1="contract_number" :default1="$internetconnection->contract_number" :label2="__('Anschlussart')" name2="connection_type" :default2="$internetconnection->connection_type" />
        <div class="flex flex-col sm:flex-row gap-2">
            <x-create.einheit :label="__('Download')" name="bandwidth_down" einheit="Mbit/s" :default="$internetconnection->bandwidth_down" />
            <x-create.einheit :label="__('Upload')" name="bandwidth_up" einheit="Mbit/s" :default="$internetconnection->bandwidth_up" />
        </div>
        <x-create.doublerow :label1="__('WAN-IP')" name1="wan_ip" :default1="$internetconnection->wan_ip" :label2="__('Hotline')" name2="hotline" :default2="$internetconnection->hotline" />
        <x-create.doublerow :label1="__('Netz (optional, z. B. 203.0.113.16/28)')" name1="subnet" :default1="$internetconnection->subnet"
            :label2="__('Gateway des Netzes')" name2="subnet_gateway" :default2="$internetconnection->subnet_gateway" />
        <x-create.doublerow :label1="__('Einwahl-Benutzer (PPPoE)')" name1="pppoe_user" :default1="$internetconnection->pppoe_user" :label2="__('Einwahl-Passwort')" name2="pppoe_password" :default2="$internetconnection->pppoe_password" />
        <x-create.singlerow :label="__('Notizen')" name="notes" :default="$internetconnection->notes" />
    </x-create.main>
    @can('internetconnection_delete')
        <x-deletecard action="{{ route('internetconnection.destroy', [$customer, $internetconnection]) }}" />
    @endcan
</x-app-layout>
