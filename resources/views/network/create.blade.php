<x-app-layout :$customer>
    <x-create.main :header="__('Neues Netzwerk')" action="{{ route('network.store', $customer) }}">

        <x-create.select name="site_id" :value="__('Standort')" :array="$sites" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" />

        <x-create.doublerow14 :label1="__('Netzwerk')" name1="network" :label2="__('VLAN ID')" name2="vlanId" default2="1"
            type2="number" />

        <x-create.doublerow14 :label1="__('Subnetzmaske')" name1="subnetmask" default1="255.255.255.0" :label2="__('CIDR')"
            name2="cidr" default2="24" type2="number" />

        <x-create.singlerow :label="__('Gateway')" name="gateway" />

        <x-create.doublerow :label1="__('DNS 1')" name1="dns1" :label2="__('DNS 2')" name2="dns2" />

        <x-create.doublerow :label1="__('DHCP-Start')" name1="dhcpStart" :label2="__('DHCP-Ende')" name2="dhcpEnd" />

    </x-create.main>
</x-app-layout>
