<x-app-layout :$customer>

    <x-sitetopmenu can="printer_create" />

    @forelse ($printers as $printer)

        @php
            $adressen = $printer->relationLoaded('ipAddresses') ? $printer->ipAddresses : $printer->ipAddresses()->get();
            $primaer = $adressen->first()?->address;
            $anzahlIps = $adressen->count();
        @endphp
    <x-card>
        <x-slot:head>
            <x-show.header can="printer_update" editUrl="{{ route('printer.edit', [$customer, $printer]) }}">
                {{ $printer->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-copy :value="$primaer" />
                            </x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
        </x-slot>

        <x-slot:body>


            <x-ipcard :device="$printer" />

            <x-minitablecard :title="__('Allgemein')" :array="[
                'Hersteller' => $printer->manufacturer,
                'Modell' => $printer->model,
                'Seriennummer' => $printer->serialNumber,
            ]" />

            <x-credentialscard :device="$printer" />

            <x-minitablecard :title="__('Netzwerk')" :array="[
                'Port' => $printer->port,
            ]" />

            <x-minitablecard :title="__('Login')" :array="[
                'Benutzer' => $printer->username,
                'Passwort' => $printer->password,
            ]" />

            <x-beschaffungcard :device="$printer" />

        </x-slot>
    </x-card>
@empty
    <x-emptystate />
@endforelse


    <div class="px-3 pb-3">
        {{ $printers->links() }}
    </div>

</x-app-layout>
