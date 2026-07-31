<x-app-layout :$customer>
    <x-create.main header="Neuer Serverschrank" action="{{ route('rack.store', $customer) }}">

        <x-create.select name="site_id" value="Standort" :array="$sites" />

        <x-create.singlerow label="Name" name="name" />

        <x-create.doublerow label1="Höheneinheiten (HE)" name1="height_units" type1="number" :default1="42"
            label2="Ort (z. B. Serverraum EG)" name2="location" />

        <x-create.singlerow label="Notiz" name="note" />

    </x-create.main>
</x-app-layout>
