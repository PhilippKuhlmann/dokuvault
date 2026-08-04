<x-app-layout :$customer>
    <x-create.main :header="__('Netzwerk bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('network.update', [$customer, $network]) }}">
        @method('PATCH')

        <x-edit.select name="site_id" :value="__('Standort')" selector="{{ $network->site_id }}" :array="$sites" />

        <x-create.singlerow :label="__('Beschreibung')" name="description" :default="$network->description" />

        <x-create.doublerow14 :label1="__('Netzwerk')" name1="network" :default1="$network->network" :label2="__('VLAN ID')" name2="vlanId" default2="1"
            type2="number" :default2="$network->vlanId"  />

        <x-create.doublerow14 :label1="__('Subnetzmaske')" name1="subnetmask" :default1="$network->subnetmask" :label2="__('CIDR')"
            name2="cidr" type2="number" :default2="$network->cidr" />

        <x-create.singlerow :label="__('Gateway')" name="gateway" :default="$network->gateway" />

        <x-create.doublerow :label1="__('DNS 1')" name1="dns1" :default1="$network->dns1" :label2="__('DNS 2')" name2="dns2" :default2="$network->dns2" />

        <x-create.doublerow :label1="__('DHCP-Start')" name1="dhcpStart" :default1="$network->dhcpStart" :label2="__('DHCP-Ende')" name2="dhcpEnd" :default2="$network->dhcpEnd" />

    </x-create.main>

    @can('network_delete')
        <x-deletecard action="{{ route('network.destroy', [$customer, $network]) }}" />
    @endcan

</x-app-layout>
